<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Jiannius\Playbook\Agents\AgentCatalog;
use Jiannius\Playbook\Agents\ManagedDirectory;
use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Contracts\SupportsSkills;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\AgentsDetector;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\GuidelineConfig;
use Laravel\Boost\Support\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('playbook:check', 'Verify this repo\'s agent files, installed skills and task agents match the current Jiannius playbook')]
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

        $unloaded = $this->claudeLoadProblems($agents);

        if ($unloaded !== []) {
            $this->components->error('Claude Code will not load the guidelines Boost writes.');
            $this->components->bulletList($unloaded);

            return self::BROKEN;
        }

        $stale = $agents->reject(fn (Agent&SupportsGuidelines $agent): bool => $this->isCurrent($agent, $expected));

        foreach ($agents as $agent) {
            $this->components->twoColumnDetail(
                $agent->displayName().' <fg=gray>'.$this->relativePath($this->resolvePath($agent->guidelinesPath())).'</>',
                $stale->contains($agent) ? '<fg=red>STALE</>' : '<fg=green>current</>'
            );
        }

        // Guidelines are half of what this package delivers. The skills are the half that
        // reaches a teammate who never runs composer, and nothing verified them until a
        // repo turned up with .claude/ in its .gitignore.
        $skills = $this->checkSkills($config, $detector);

        // The task agents travel the way the skills do: copied into a committed directory by
        // boost:update, so they fail the same three ways. A project agent that shares a name
        // with one of ours is a fourth.
        $taskAgents = $this->checkAgents($config);

        if ($skills['broken'] !== [] || $taskAgents['broken'] !== []) {
            $this->newLine();

            if ($skills['broken'] !== []) {
                $this->components->error('The skills this package ships cannot reach this repo.');
                $this->components->bulletList($skills['broken']);
            }

            if ($taskAgents['broken'] !== []) {
                $this->components->error('The task agents this package ships cannot be used in this repo.');
                $this->components->bulletList($taskAgents['broken']);
            }

            return self::BROKEN;
        }

        if ($stale->isEmpty() && $skills['stale'] === [] && $taskAgents['stale'] === []) {
            return self::OK;
        }

        $this->newLine();

        if ($stale->isNotEmpty()) {
            $this->components->error(sprintf('%d agent file(s) are behind %s.', $stale->count(), self::PACKAGE));

            if ($this->option('diff')) {
                $this->showExpected($expected);
            }
        }

        if ($skills['stale'] !== []) {
            $this->components->error(sprintf('%d installed skill(s) are behind %s.', count($skills['stale']), self::PACKAGE));
            $this->components->bulletList($skills['stale']);
        }

        if ($taskAgents['stale'] !== []) {
            $this->components->error(sprintf('%d task agent file(s) are behind %s.', count($taskAgents['stale']), self::PACKAGE));
            $this->components->bulletList($taskAgents['stale']);
        }

        $this->components->bulletList(['Fix: run [php artisan boost:update] and commit the result, including .claude/agents/ and .claude/rules/.']);

        return self::STALE;
    }

    /**
     * Verify the skills half of the delivery: installed, faithful to the source, and
     * reachable by someone who only ever clones this repo.
     *
     * @return array{broken: list<string>, stale: list<string>}
     */
    protected function checkSkills(Config $config, AgentsDetector $detector): array
    {
        $shipped = $this->shippedSkills();

        if ($shipped === []) {
            return [
                'broken' => [sprintf('%s ships no skills. Is resources/boost/skills/ present in the installed package?', self::PACKAGE)],
                'stale' => [],
            ];
        }

        $agents = $this->skillAgents($config, $detector);

        // Every agent Boost knows implements SupportsSkills, each with its own directory
        // (.claude/skills; .agents/skills for Codex, OpenCode, Amp, Zed; .github/skills
        // for Copilot), so a repo with two agents enabled has two places the skills have
        // to land. This guard covers the case where the configured list resolves to no
        // agent at all, which the guidelines check above already reports.
        if ($agents->isEmpty()) {
            return ['broken' => [], 'stale' => []];
        }

        // The trap worth failing loudly for: boost.json names this package under
        // "packages" but carries no "skills" list, so boost:update composes the
        // guidelines and installs no skills — on every run, forever, reporting success
        // each time. Invisible in every repo it hits.
        if (! $config->hasSkills()) {
            return [
                'broken' => [
                    'boost.json has no "skills" list, so [boost:update] installs no skills at all — and still reports success.',
                    'Fix: run [php artisan boost:install] and tick Agent Skills, or add the skill names to "skills" in boost.json.',
                ],
                'stale' => [],
            ];
        }

        $broken = [];
        $stale = [];

        foreach ($agents as $agent) {
            foreach ($shipped as $name => $source) {
                $target = base_path($agent->skillsPath().DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'SKILL.md');
                $label = $agent->displayName().' <fg=gray>'.$this->relativePath($target).'</>';
                $shown = $this->relativePath($target);

                if (! is_file($target)) {
                    $stale[] = $shown.' is not installed.';
                    $this->components->twoColumnDetail($label, '<fg=red>MISSING</>');

                    continue;
                }

                $installed = (string) file_get_contents($target);
                $packaged = (string) file_get_contents($source);

                if ($installed !== $packaged) {
                    $stale[] = sprintf(
                        '%s differs from the packaged source (installed %d bytes, packaged %d).',
                        $shown,
                        strlen($installed),
                        strlen($packaged)
                    );
                    $this->components->twoColumnDetail($label, '<fg=red>STALE</>');

                    continue;
                }

                if ($this->isUnreachableByClone($target)) {
                    $broken[] = sprintf('%s is gitignored and untracked — Boost writes it, git discards it, and a teammate who clones this repo gets nothing.', $shown);
                    $broken[] = 'Fix: stop ignoring '.$this->relativePath(base_path($agent->skillsPath())).' — ignore only the per-person files under it — then commit the skills.';
                    $this->components->twoColumnDetail($label, '<fg=red>IGNORED</>');

                    continue;
                }

                $this->components->twoColumnDetail($label, '<fg=green>current</>');
            }
        }

        return ['broken' => $broken, 'stale' => $stale];
    }

    /**
     * Verify the task agents and their routing rule: present, faithful to the source, a
     * regular file, nothing left over from a retired agent, and no project agent that shares
     * a name with one of ours.
     *
     * Reads AgentCatalog, as the writer does, so the two cannot disagree. Writes nothing.
     *
     * @return array{broken: list<string>, stale: list<string>}
     */
    protected function checkAgents(Config $config): array
    {
        $broken = [];
        $stale = [];

        if (! in_array('claude_code', $config->getAgents(), true)) {
            $this->components->twoColumnDetail('Task agents', '<fg=gray>skipped: Claude Code not enabled</>');

            return ['broken' => $broken, 'stale' => $stale];
        }

        if (AgentCatalog::shipped() === []) {
            return [
                'broken' => [sprintf('%s ships no agents. Is resources/claude/agents/ present in the installed package?', self::PACKAGE)],
                'stale' => [],
            ];
        }

        $rendered = [];

        foreach (AgentCatalog::rendered() as $name => $source) {
            $rendered[$name.'.md'] = $source;
        }

        $sets = [
            AgentCatalog::AGENTS_DIR => ['label' => 'Task agent', 'expected' => $rendered],
            AgentCatalog::RULES_DIR => ['label' => 'Task-agent rule', 'expected' => AgentCatalog::rules()],
        ];

        if (! AgentCatalog::enabled()) {
            $present = 0;

            foreach (array_keys($sets) as $dir) {
                // A symlinked managed directory is something to report, not "nothing installed".
                $present += ManagedDirectory::refusal(base_path($dir)) !== null ? 1 : count($this->managedFiles($dir));
            }

            if ($present === 0) {
                $this->components->twoColumnDetail('Task agents', '<fg=gray>disabled</>');

                return ['broken' => $broken, 'stale' => $stale];
            }
        }

        foreach ($sets as $dir => $set) {
            $ignoredReported = false;

            // Never follow a symlinked managed directory. Sync leaves it alone too, so
            // boost:update will not fix this: the person has to replace the link.
            $refusal = ManagedDirectory::refusal(base_path($dir));

            if ($refusal !== null) {
                $stale[] = sprintf(
                    '%s %s. playbook does not follow it and Claude Code does not load symlinked project files. Replace it with a real directory, then run boost:update.',
                    $dir,
                    $refusal
                );
                $this->components->twoColumnDetail($set['label'].' <fg=gray>'.$dir.'</>', '<fg=red>STALE</>');

                continue;
            }

            foreach ($set['expected'] as $name => $source) {
                $target = base_path($dir.DIRECTORY_SEPARATOR.$name);
                $shown = $dir.'/'.$name;
                $label = $set['label'].' <fg=gray>'.$shown.'</>';

                if (is_link($target)) {
                    $stale[] = $shown.' is a symlink; Claude Code does not load symlinked project files. Run boost:update.';
                    $this->components->twoColumnDetail($label, '<fg=red>STALE</>');

                    continue;
                }

                if (! is_file($target)) {
                    $stale[] = $shown.' is not installed.';
                    $this->components->twoColumnDetail($label, '<fg=red>MISSING</>');

                    continue;
                }

                $installed = (string) file_get_contents($target);
                $packaged = (string) file_get_contents($source);

                if ($installed !== $packaged) {
                    $stale[] = sprintf(
                        '%s differs from the packaged source (installed %d bytes, packaged %d).',
                        $shown,
                        strlen($installed),
                        strlen($packaged)
                    );
                    $this->components->twoColumnDetail($label, '<fg=red>STALE</>');

                    continue;
                }

                if ($this->isUnreachableByClone($target)) {
                    // One message per directory: the same ignore rule hides every file in it.
                    if (! $ignoredReported) {
                        $broken[] = sprintf('%s is gitignored and untracked — Boost writes it, git discards it, and a teammate who clones this repo gets nothing.', $dir);
                        $broken[] = 'Fix: stop ignoring '.$dir.' — ignore only the per-person files under .claude/ — then commit it.';
                        $ignoredReported = true;
                    }

                    $this->components->twoColumnDetail($label, '<fg=red>IGNORED</>');

                    continue;
                }

                $this->components->twoColumnDetail($label, '<fg=green>current</>');
            }

            foreach ($this->managedFiles($dir) as $name) {
                if (array_key_exists($name, $set['expected'])) {
                    continue;
                }

                $shown = $dir.'/'.$name;

                $stale[] = $shown.' is not shipped by '.self::PACKAGE.' (retired, or opted out). Run boost:update.';
                $this->components->twoColumnDetail($set['label'].' <fg=gray>'.$shown.'</>', '<fg=red>RETIRED</>');
            }
        }

        if (AgentCatalog::enabled()) {
            $renderedNames = AgentCatalog::rendered();
            $except = AgentCatalog::except();

            foreach (AgentCatalog::projectAgentFiles() as $path) {
                $name = AgentCatalog::frontmatterName($path);

                if ($name === null) {
                    continue;
                }

                $shown = $this->relativePath($path);

                if (array_key_exists($name, $renderedNames)) {
                    $broken[] = sprintf(
                        '%1$s has name %2$s, the same as a playbook agent. Claude Code will load one of the two at random. Opt out of %2$s in config/playbook.php to keep yours, or delete it.',
                        $shown,
                        $name
                    );
                    $this->components->twoColumnDetail('Project agent <fg=gray>'.$shown.'</>', '<fg=red>CLASH</>');
                } elseif (in_array($name, $except, true)) {
                    $this->components->twoColumnDetail(
                        'Project agent <fg=gray>'.$shown.'</>',
                        '<fg=gray>'.$name.' overridden by the project</>'
                    );
                }
            }
        }

        return ['broken' => $broken, 'stale' => $stale];
    }

    /**
     * The files directly inside a managed directory, by name. A subdirectory is not a file
     * playbook wrote, and the sync leaves it alone, so the check does too.
     *
     * @return list<string>
     */
    protected function managedFiles(string $dir): array
    {
        $path = base_path($dir);

        if (! is_dir($path)) {
            return [];
        }

        return array_values(array_filter(
            array_diff(scandir($path) ?: [], ['.', '..']),
            fn (string $entry): bool => is_link($path.DIRECTORY_SEPARATOR.$entry) || is_file($path.DIRECTORY_SEPARATOR.$entry)
        ));
    }

    /**
     * The skills this package ships, read from the installed package rather than from
     * Boost's discovery. The question here is what *we* deliver, and the source file is
     * also the thing the installed copy has to match byte for byte.
     *
     * @return array<string, string> skill name => absolute path to its SKILL.md
     */
    protected function shippedSkills(): array
    {
        $skills = [];

        foreach (glob(dirname(__DIR__, 2).'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir.'/SKILL.md')) {
                $skills[basename($dir)] = $dir.'/SKILL.md';
            }
        }

        return $skills;
    }

    /**
     * @return Collection<int, Agent&SupportsSkills>
     */
    protected function skillAgents(Config $config, AgentsDetector $detector): Collection
    {
        $selected = $config->getAgents();

        return $detector->getAgents()
            ->filter(fn (Agent $agent): bool => in_array($agent->name(), $selected, true))
            ->filter(fn (Agent $agent): bool => $agent instanceof SupportsSkills)
            ->values();
    }

    /**
     * True when git would discard this file: matched by an ignore rule and not already
     * tracked. Tracked wins — a force-added file is committed whatever the pattern says,
     * and reporting that would be a false alarm.
     *
     * False whenever the answer cannot be established (no repository, no git binary): a
     * check that cannot see the repo must not fail the build.
     */
    protected function isUnreachableByClone(string $path): bool
    {
        if (! file_exists(base_path('.git'))) {
            return false;
        }

        if ($this->git(['check-ignore', '-q', '--', $path]) !== 0) {
            return false;
        }

        return $this->git(['ls-files', '--error-unmatch', '--', $path]) !== 0;
    }

    /**
     * @param  list<string>  $args
     */
    protected function git(array $args): int
    {
        $command = 'git -C '.escapeshellarg(base_path()).' '
            .implode(' ', array_map('escapeshellarg', $args)).' 2>&1';

        $code = 0;
        $output = [];
        exec($command, $output, $code);

        return $code;
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

    /**
     * Boost 2.10 writes Claude Code's guidelines to AGENTS.md, but Claude Code only loads
     * AGENTS.md when there is no CLAUDE.md — and every Jiannius repo has one. Without an import,
     * boost:update keeps AGENTS.md current while Claude reads whatever CLAUDE.md last held, and
     * the staleness check above would pass, because AGENTS.md really is current.
     *
     * @param  Collection<int, Agent&SupportsGuidelines>  $agents
     * @return list<string>
     */
    protected function claudeLoadProblems(Collection $agents): array
    {
        $claude = $agents->first(fn (Agent $agent): bool => $agent->name() === 'claude_code');
        $claudeMd = base_path('CLAUDE.md');

        if ($claude === null || $this->resolvePath($claude->guidelinesPath()) === $claudeMd || ! is_file($claudeMd)) {
            return [];
        }

        $target = $claude->guidelinesPath();
        $content = (string) file_get_contents($claudeMd);
        $problems = [];

        if (preg_match('/^@'.preg_quote($target, '/').'[ \t]*$/m', $content) !== 1) {
            $problems[] = sprintf('CLAUDE.md does not import %1$s, and Claude Code never loads %1$s while CLAUDE.md exists. Fix: move the constitution from CLAUDE.md to below the guidelines block in %1$s, then reduce CLAUDE.md to the single line [@%1$s].', $target);
        }

        if (preg_match(self::BLOCK, $content) === 1) {
            $problems[] = sprintf('CLAUDE.md still holds a guidelines block that boost:update no longer rewrites. Fix: delete it; %s carries the current one.', $target);
        }

        return $problems;
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
