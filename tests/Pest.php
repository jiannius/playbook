<?php

declare(strict_types=1);
use Jiannius\Playbook\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Shared fixtures
|--------------------------------------------------------------------------
|
| These live here rather than in a test file because Pest loads Pest.php on
| every run, including a filtered one. A helper declared inside a test file is
| only defined when that file happens to be loaded, which is how this suite
| ended up with three near-identical boost.json builders: a filtered run could
| not see the first one, so the next test file declared its own.
|
*/

/**
 * A boost.json with Boost set up, playbook enabled, and skills switched on.
 *
 * The skills list is non-empty on purpose. An empty one is its own failure —
 * boost:update installs no skills at all and still reports success — so a
 * fixture carrying `'skills' => []` describes a broken repo rather than a
 * neutral default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function boostJson(array $overrides = []): array
{
    return array_merge([
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['jiannius/playbook'],
        'mcp' => false,
        'skills' => ['jiannius-dev', 'jiannius-qa-tester'],
    ], $overrides);
}

/**
 * Wrap text the way Boost's GuidelineWriter does.
 *
 * One copy, because CheckCommand matches this shape with a regex. A second
 * hand-written copy of the delimiters is worse than no helper: if Boost ever
 * reshapes them, the copy stops matching, the command reports STALE for the
 * wrong reason, and a test asserting STALE stays green while exercising a
 * different path entirely.
 */
function boostBlock(string $inner): string
{
    return "<laravel-boost-guidelines>\n".$inner."\n\n</laravel-boost-guidelines>\n";
}

/**
 * Make the guidelines half of playbook:check current, so a test about another half is not
 * also failing this one.
 */
function agentsGuidelinesCurrent(string $dir, string $rendered, string $file = 'CLAUDE.md'): void
{
    file_put_contents($dir.'/'.$file, boostBlock($rendered));
}

/** Run a version-control command inside a fixture project. */
function agentsGit(string $dir, string $args): void
{
    exec('git -C '.escapeshellarg($dir).' '.$args.' 2>&1');
}

/** A project-owned agent file under .claude/agents/ with the given frontmatter name. */
function projectAgent(string $dir, string $relative, string $name): string
{
    $path = $dir.'/.claude/agents/'.$relative;

    is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);
    file_put_contents($path, "---\nname: {$name}\ndescription: Mine.\n---\n\nProject-owned agent {$name}.\n");

    return $path;
}
