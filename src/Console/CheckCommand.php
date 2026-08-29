<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\AgentsDetector;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\GuidelineConfig;
use Laravel\Boost\Support\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('playbook:check', 'Verify this repo\'s agent files carry the current Jiannius playbook guidelines')]
class CheckCommand extends Command
{
    /** @var string */
    protected $signature = 'playbook:check {--diff : Show what differs instead of just naming the file}';

    /** The composer package whose guidelines this command verifies. */
    public const PACKAGE = 'jiannius/playbook';

    /** Boost wraps everything it manages in this block. */
    private const BLOCK = '/<laravel-boost-guidelines>(.*?)<\/laravel-boost-guidelines>/s';

    /** Everything is current. */
    public const OK = 0;

    /** At least one agent file is stale — run boost:update and commit. */
    public const STALE = 1;

    /** The check itself could not run. Distinct from STALE so CI can tell them apart. */
    public const BROKEN = 2;

    public function handle(Config $config, AgentsDetector $detector): int
    {
        if (! $config->isValid()) {
            $this->components->error('Boost is not set up here. Run [php artisan boost:install] first.');

            return self::BROKEN;
        }

        if (! in_array(self::PACKAGE, $config->getPackages(), true)) {
            $this->components->error(sprintf(
                '%s is installed but not enabled in boost.json. Add it to "packages", or run [php artisan boost:update --discover].',
                self::PACKAGE
            ));

            return self::BROKEN;
        }

        try {
            $expected = $this->expectedGuidelines();
        } catch (Throwable $e) {
            $this->components->error('Could not compose guidelines: '.$e->getMessage());

            return self::BROKEN;
        }

        if ($expected->isEmpty()) {
            $this->components->error(sprintf(
                'Boost composed no guidelines for %s. Is resources/boost/guidelines/ present in the installed package?',
                self::PACKAGE
            ));

            return self::BROKEN;
        }

        $agents = $this->guidelineAgents($config, $detector);

        if ($agents->isEmpty()) {
            $this->components->error('No agents in boost.json support guidelines. Run [php artisan boost:install].');

            return self::BROKEN;
        }

        $stale = $agents->reject(fn (Agent&SupportsGuidelines $agent): bool => $this->isCurrent($agent, $expected));

        foreach ($agents as $agent) {
            $this->components->twoColumnDetail(
                $agent->displayName().' <fg=gray>'.$this->relativePath($this->resolvePath($agent->guidelinesPath())).'</>',
                $stale->contains($agent) ? '<fg=red>STALE</>' : '<fg=green>current</>'
            );
        }

        if ($stale->isEmpty()) {
            return self::OK;
        }

        $this->newLine();
        $this->components->error(sprintf('%d agent file(s) are behind %s.', $stale->count(), self::PACKAGE));

        if ($this->option('diff')) {
            $this->showExpected($expected);
        }

        $this->components->bulletList(['Fix: run [php artisan boost:update] and commit the result.']);

        return self::STALE;
    }

    /**
     * The rendered guideline text Boost would write for this package.
     *
     * Only this package's entry is extracted, deliberately. Reproducing Boost's entire
     * composed output would mean reconstructing install-time answers that boost.json does
     * not persist (enforceTests among them), and a mismatch there would report false
     * staleness. This asks the narrower question that actually matters: is *our* guidance
     * in the file?
     */
    protected function expectedGuidelines(): Collection
    {
        $guidelineConfig = new GuidelineConfig;
        // aiGuidelines is left unset on purpose: getThirdPartyGuidelines() returns every
        // discovered third-party package when it is not set, which is what we want here.

        return $this->laravel->make(GuidelineComposer::class)
            ->config($guidelineConfig)
            ->guidelines()
            // Boost keys third-party guidelines as "<package>/<file>" — jiannius/playbook/core
            // for core.blade.php. Match by prefix so extra guideline files are picked up too.
            // (Boost 2.3 keyed them as the bare package name; both shapes are accepted.)
            ->filter(fn (mixed $g, string $key): bool => $key === self::PACKAGE || str_starts_with($key, self::PACKAGE.'/'))
            ->map(fn (mixed $g): string => trim((string) (is_array($g) ? ($g['content'] ?? '') : '')))
            ->reject(fn (string $content): bool => $content === '');
    }

    /**
     * @return Collection<int, Agent&SupportsGuidelines>
     */
    protected function guidelineAgents(Config $config, AgentsDetector $detector): Collection
    {
        $selected = $config->getAgents();

        return $detector->getAgents()
            ->filter(fn (Agent $agent): bool => in_array($agent->name(), $selected, true))
            ->filter(fn (Agent $agent): bool => $agent instanceof SupportsGuidelines)
            ->values();
    }

    /** @param  Collection<string, string>  $expected */
    protected function isCurrent(Agent&SupportsGuidelines $agent, Collection $expected): bool
    {
        $path = $this->resolvePath($agent->guidelinesPath());

        if (! is_file($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        if (preg_match(self::BLOCK, $content, $matches) !== 1) {
            return false;
        }

        $block = $this->normalise($matches[1]);

        // Compare against the agent's own transform — Codex and OpenCode reshape the text.
        return $expected->every(fn (string $guideline): bool => str_contains(
            $block,
            $this->normalise($agent->transformGuidelines($guideline))
        ));
    }

    /** Collapse whitespace so trailing-space churn is not reported as staleness. */
    protected function normalise(string $text): string
    {
        return trim((string) preg_replace('/[ \t]+\n/', "\n", str_replace("\r\n", "\n", $text)));
    }

    /**
     * Boost's agents return a path relative to the project root (plain 'CLAUDE.md'), and its
     * writer relies on the process CWD being that root — true for `php artisan`, not
     * guaranteed when CI invokes from elsewhere. Anchor to base_path() so the check reads
     * the same file the writer wrote.
     */
    protected function resolvePath(string $path): string
    {
        if ($path === '' || $path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return base_path($path);
    }

    protected function relativePath(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /** @param  Collection<string, string>  $expected */
    protected function showExpected(Collection $expected): void
    {
        foreach ($expected as $key => $guideline) {
            $this->newLine();
            $this->line('<fg=gray>Expected to find ['.$key.'] inside the guidelines block:</>');
            $this->newLine();

            foreach (explode("\n", $guideline) as $line) {
                $this->line('<fg=green>+ </><fg=gray>'.$line.'</>');
            }
        }

        $this->newLine();
    }
}
