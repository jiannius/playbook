<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Agents;

/**
 * Brings one directory to exactly the set of files playbook ships into it.
 *
 * The directory is the managed boundary: every file directly inside belongs to playbook and
 * is rewritten or removed to match, and nothing outside it is ever touched. That includes
 * what a symlink points at: a managed directory that is a symlink, or that sits under one
 * leading outside .claude/, is left alone entirely.
 *
 * Files are copied, never symlinked. Claude Code 2.1.301 does not load a symlinked file
 * from .claude/rules/, so a symlink would be silently inert.
 */
final class ManagedDirectory
{
    public const WRITTEN = 'written';

    public const UPDATED = 'updated';

    public const CURRENT = 'current';

    public const REMOVED = 'removed';

    /**
     * @param  array<string, string>  $expected  filename => absolute path of the source to copy
     * @return array{files: array<string, string>, warnings: list<string>} filename => status, and anything left alone
     */
    public function sync(string $dir, array $expected): array
    {
        $files = [];
        $warnings = [];

        $refusal = self::refusal($dir);

        if ($refusal !== null) {
            return ['files' => $files, 'warnings' => [sprintf('%s %s, left alone.', $dir, $refusal)]];
        }

        if ($expected === [] && ! is_dir($dir) && ! is_link($dir)) {
            return ['files' => $files, 'warnings' => $warnings];
        }

        if ($expected !== [] && ! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($expected as $name => $source) {
            $target = $dir.DIRECTORY_SEPARATOR.$name;
            $bytes = (string) file_get_contents($source);

            $existed = is_link($target) || file_exists($target);
            $current = ! is_link($target) && is_file($target) && file_get_contents($target) === $bytes;

            if ($current) {
                $files[$name] = self::CURRENT;

                continue;
            }

            // A symlink, or anything that is not a regular file, goes before the write.
            // Writing through a symlink would edit whatever it points at.
            if (is_link($target) || (file_exists($target) && ! is_file($target))) {
                $this->delete($target);
            }

            file_put_contents($target, $bytes);

            $files[$name] = $existed ? self::UPDATED : self::WRITTEN;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || array_key_exists($entry, $expected)) {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$entry;

            if (! is_link($path) && is_dir($path)) {
                $warnings[] = sprintf('%s is a directory inside a playbook-managed directory. Left alone.', $path);

                continue;
            }

            unlink($path);
            $files[$entry] = self::REMOVED;
        }

        if ($expected === [] && (scandir($dir) ?: []) === ['.', '..']) {
            rmdir($dir);
        }

        return ['files' => $files, 'warnings' => $warnings];
    }

    /**
     * Why sync will not touch this directory, or null when it is safe to manage.
     *
     * Following a symlink would write the agents into, and prune foreign files from, whatever
     * it points at. So the directory itself may not be a link, and wherever it resolves (a
     * symlinked parent counts) must still be inside .claude/.
     */
    public static function refusal(string $dir): ?string
    {
        if (is_link($dir)) {
            return 'is a symlink';
        }

        $boundary = self::resolve(base_path('.claude'));
        $resolved = self::resolve($dir);

        if ($boundary === null || $resolved === null) {
            return 'cannot be resolved (a broken symlink in its path)';
        }

        if ($resolved !== $boundary && ! str_starts_with($resolved, $boundary.DIRECTORY_SEPARATOR)) {
            return 'resolves outside .claude/ (a parent directory is a symlink)';
        }

        return null;
    }

    /**
     * The real path of a directory that may not exist yet: the nearest existing ancestor
     * resolved, with the missing tail put back. Null when the path runs into a broken symlink.
     */
    private static function resolve(string $path): ?string
    {
        $tail = [];

        while (($real = realpath($path)) === false) {
            if (is_link($path)) {
                return null;
            }

            $parent = dirname($path);

            if ($parent === $path) {
                return null;
            }

            array_unshift($tail, basename($path));
            $path = $parent;
        }

        return implode(DIRECTORY_SEPARATOR, [rtrim($real, DIRECTORY_SEPARATOR), ...$tail]);
    }

    private function delete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->delete($path.DIRECTORY_SEPARATOR.$entry);
            }
        }

        rmdir($path);
    }
}
