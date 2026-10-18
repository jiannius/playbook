<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Agents;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * What this package ships for Claude Code, minus what the repo opted out of.
 *
 * The writer (AgentSync) and the gate (playbook:check) both read this class, so they
 * cannot disagree about which files should exist.
 */
final class AgentCatalog
{
    /** The last line of every shipped file. Marks the file as managed. */
    public const NOTICE = '<!-- Managed by jiannius/playbook and rewritten by boost:update. Change it in the package, or opt out in config/playbook.php and keep your own copy outside this directory. -->';

    /** Where the agents land in a repo. Everything directly inside is playbook's. */
    public const AGENTS_DIR = '.claude/agents/playbook';

    /** Where the routing rule lands in a repo. Everything directly inside is playbook's. */
    public const RULES_DIR = '.claude/rules/playbook';

    public static function enabled(): bool
    {
        return (bool) config('playbook.agents.enabled', true);
    }

    /**
     * @return list<string>
     */
    public static function except(): array
    {
        return array_values(array_map('strval', (array) config('playbook.agents.except', [])));
    }

    /**
     * Every agent the package ships, whatever the repo's settings say.
     *
     * @return array<string, string> agent name => absolute path to its source
     */
    public static function shipped(): array
    {
        return self::sources('agents');
    }

    /**
     * The agents this repo should have: shipped, minus opt-outs, or none when disabled.
     *
     * @return array<string, string> agent name => absolute path to its source
     */
    public static function rendered(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return array_diff_key(self::shipped(), array_flip(self::except()));
    }

    /**
     * The rules this repo should have, or none when disabled.
     *
     * @return array<string, string> filename => absolute path to its source
     */
    public static function rules(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $rules = [];

        foreach (self::sources('rules') as $name => $path) {
            $rules[$name.'.md'] = $path;
        }

        return $rules;
    }

    /**
     * Names in `except` that this package does not ship, almost always a typo.
     *
     * @return list<string>
     */
    public static function unknownExclusions(): array
    {
        return array_values(array_diff(self::except(), array_keys(self::shipped())));
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

        $managed = base_path(self::AGENTS_DIR).DIRECTORY_SEPARATOR;
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

    /**
     * The `name` in a file's frontmatter. Claude Code identifies an agent by this, not by the filename.
     */
    public static function frontmatterName(string $path): ?string
    {
        $content = @file_get_contents($path);

        if ($content === false || preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $content, $matches) !== 1) {
            return null;
        }

        try {
            $frontmatter = Yaml::parse($matches[1]);
        } catch (ParseException) {
            return null;
        }

        $name = is_array($frontmatter) ? ($frontmatter['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Resolved from the package directory, not from Boost's discovery: the question is
     * what we deliver, and the source file is what an installed copy must match.
     *
     * @return array<string, string> filename without extension => absolute path
     */
    private static function sources(string $kind): array
    {
        $sources = [];
        $files = glob(dirname(__DIR__, 2).'/resources/claude/'.$kind.'/*.md') ?: [];

        sort($files);

        foreach ($files as $path) {
            $sources[basename($path, '.md')] = $path;
        }

        return $sources;
    }
}
