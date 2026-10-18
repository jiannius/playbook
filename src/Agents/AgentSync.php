<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Agents;

use FilesystemIterator;
use Laravel\Boost\Support\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes this repo's Claude Code task agents and routing rule.
 *
 * Used by `playbook:sync` and by the listener that runs it after boost:update. It never
 * fails the run over a clash or a warning: `playbook:check` is the gate.
 */
class AgentSync
{
    public const OK = 0;

    /** The sync could not run: Boost is not set up here. */
    public const BROKEN = 2;

    public function __construct(
        private readonly Config $config,
        private readonly ManagedDirectory $directory = new ManagedDirectory,
    ) {}

    public function run(OutputInterface $output): int
    {
        if (! $this->config->isValid()) {
            $output->writeln('<error>Boost is not set up here. Run [php artisan boost:install] first.</error>');

            return self::BROKEN;
        }

        if (! in_array('claude_code', $this->config->getAgents(), true)) {
            $output->writeln('playbook:sync skipped: Claude Code is not enabled in boost.json');

            return self::OK;
        }

        $this->report($output, AgentCatalog::AGENTS_DIR, $this->directory->sync(
            base_path(AgentCatalog::AGENTS_DIR),
            $this->withFilenames(AgentCatalog::rendered())
        ));

        $this->report($output, AgentCatalog::RULES_DIR, $this->directory->sync(
            base_path(AgentCatalog::RULES_DIR),
            AgentCatalog::rules()
        ));

        $this->reportClashes($output);

        foreach (AgentCatalog::unknownExclusions() as $name) {
            $output->writeln(sprintf(
                '<comment>warning:</comment> `%s` is in agents.except in config/playbook.php, but playbook ships no agent by that name.',
                $name
            ));
        }

        return self::OK;
    }

    /**
     * @param  array<string, string>  $agents  name => source
     * @return array<string, string> filename => source
     */
    private function withFilenames(array $agents): array
    {
        $files = [];

        foreach ($agents as $name => $source) {
            $files[$name.'.md'] = $source;
        }

        return $files;
    }

    /**
     * @param  array{files: array<string, string>, warnings: list<string>}  $result
     */
    private function report(OutputInterface $output, string $dir, array $result): void
    {
        foreach ($result['files'] as $name => $status) {
            $output->writeln(sprintf('  %s/%s %s', $dir, $name, $status));
        }

        foreach ($result['warnings'] as $warning) {
            $output->writeln('<comment>warning:</comment> '.$this->relative($warning));
        }
    }

    /**
     * Claude Code identifies an agent by its frontmatter `name`, not its path, and a project
     * agent with the same name as ours is loaded in place of it or beside it. Look for those
     * anywhere under .claude/agents/ except the directory playbook owns.
     */
    private function reportClashes(OutputInterface $output): void
    {
        if (! AgentCatalog::enabled()) {
            return;
        }

        $rendered = AgentCatalog::rendered();
        $except = AgentCatalog::except();

        foreach (self::projectAgentFiles() as $path) {
            $name = AgentCatalog::frontmatterName($path);

            if ($name === null) {
                continue;
            }

            if (array_key_exists($name, $rendered)) {
                $output->writeln(sprintf(
                    '<comment>warning:</comment> `%1$s` has name `%2$s`, the same as a playbook agent. Claude Code will load one of the two at random. Opt out of `%2$s` in config/playbook.php to keep yours, or delete it.',
                    $this->relative($path),
                    $name
                ));
            } elseif (in_array($name, $except, true)) {
                $output->writeln(sprintf('`%s` overridden by the project (`%s`)', $name, $this->relative($path)));
            }
        }
    }

    /**
     * Every `*.md` under .claude/agents/ that playbook does not own.
     *
     * @return list<string>
     */
    public static function projectAgentFiles(): array
    {
        $root = base_path('.claude/agents');

        if (! is_dir($root)) {
            return [];
        }

        $managed = base_path(AgentCatalog::AGENTS_DIR).DIRECTORY_SEPARATOR;
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_starts_with($path, $managed) || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
