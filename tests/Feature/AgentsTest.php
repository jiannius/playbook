<?php

declare(strict_types=1);

use Jiannius\Playbook\Agents\AgentCatalog;
use Symfony\Component\Yaml\Yaml;

/** The shipped agent sources, keyed by filename without the extension. */
function shippedAgentFiles(): array
{
    $root = dirname(__DIR__, 2).'/resources/claude/agents';

    return collect(glob($root.'/*.md') ?: [])
        ->mapWithKeys(fn (string $path): array => [basename($path, '.md') => $path])
        ->all();
}

/** @return array<string, mixed> */
function agentFrontmatter(string $path): array
{
    $content = (string) file_get_contents($path);

    expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $content, $matches))
        ->toBe(1, basename($path).' has no frontmatter block');

    $parsed = Yaml::parse($matches[1]);

    expect($parsed)->toBeArray();

    return $parsed;
}

it('ships exactly the eleven task agents', function () {
    expect(array_keys(shippedAgentFiles()))->toEqualCanonicalizing([
        'architect',
        'auditor',
        'data-backfill',
        'data-import',
        'data-investigator',
        'debugger',
        'dependency-updater',
        'plan-executor',
        'pr-fixer',
        'pr-reviewer',
        'qa-tester',
    ]);
});

it('gives every agent frontmatter that parses', function () {
    foreach (shippedAgentFiles() as $name => $path) {
        expect(agentFrontmatter($path))->not->toBeEmpty("{$name} frontmatter is empty");
    }
});

it('names each agent after its file, with no duplicates', function () {
    $names = [];

    foreach (shippedAgentFiles() as $file => $path) {
        $name = agentFrontmatter($path)['name'] ?? null;

        expect($name)->toBe($file, "{$file} frontmatter name must match its filename");

        $names[] = $name;
    }

    expect($names)->toBe(array_values(array_unique($names)));
});

it('gives every agent a description', function () {
    foreach (shippedAgentFiles() as $name => $path) {
        $description = agentFrontmatter($path)['description'] ?? null;

        expect(is_string($description) && trim($description) !== '')->toBeTrue("{$name} has no description");
    }
});

it('uses only a model and an effort Claude Code accepts', function () {
    foreach (shippedAgentFiles() as $name => $path) {
        $frontmatter = agentFrontmatter($path);

        expect(in_array($frontmatter['model'] ?? null, ['opus', 'sonnet', 'haiku', 'fable', 'inherit'], true))
            ->toBeTrue("{$name} has an unknown model");
        expect(in_array($frontmatter['effort'] ?? null, ['low', 'medium', 'high', 'xhigh', 'max'], true))
            ->toBeTrue("{$name} has an unknown effort");
    }
});

it('pins the agreed models and efforts', function () {
    $agents = shippedAgentFiles();

    $pinned = [
        'architect' => ['opus', 'xhigh'],
        'debugger' => ['opus', 'high'],
        'qa-tester' => ['sonnet', 'medium'],
    ];

    foreach ($pinned as $name => [$model, $effort]) {
        $frontmatter = agentFrontmatter($agents[$name]);

        expect($frontmatter['model'])->toBe($model, "{$name} model");
        expect($frontmatter['effort'])->toBe($effort, "{$name} effort");
    }
});

it('preloads only skills this package ships', function () {
    $skills = collect(glob(dirname(__DIR__, 2).'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [])
        ->map(fn (string $dir): string => basename($dir))
        ->all();

    foreach (shippedAgentFiles() as $name => $path) {
        $declared = agentFrontmatter($path)['skills'] ?? [];

        foreach ((array) $declared as $skill) {
            expect(in_array($skill, $skills, true))
                ->toBeTrue("{$name} preloads {$skill}, which playbook does not ship");
        }
    }
});

it('ends every agent with the managed notice', function () {
    $notice = AgentCatalog::NOTICE;

    foreach (shippedAgentFiles() as $name => $path) {
        $lines = array_values(array_filter(
            explode("\n", (string) file_get_contents($path)),
            fn (string $line): bool => trim($line) !== ''
        ));

        expect(end($lines))->toBe($notice, "{$name} does not end with the notice");
    }
});

it('keeps machine paths and a bare CLAUDE.md out of every agent', function () {
    foreach (shippedAgentFiles() as $name => $path) {
        $content = (string) file_get_contents($path);

        expect(str_contains($content, '/Users/'))->toBeFalse("{$name} names a home directory");
        expect(str_contains($content, '~/.claude'))->toBeFalse("{$name} names ~/.claude");

        expect(str_contains(str_replace('CLAUDE.md / AGENTS.md', '', $content), 'CLAUDE.md'))
            ->toBeFalse("{$name} mentions CLAUDE.md outside 'CLAUDE.md / AGENTS.md'");
    }
});

it('allows staging for the two data agents, and never production', function () {
    $agents = shippedAgentFiles();

    foreach (['data-backfill', 'data-investigator'] as $name) {
        expect((string) file_get_contents($agents[$name]))
            ->toContain('local or staging (only if explicitly given), never production');
    }
});

it('keeps client system names out of data-investigator', function () {
    $content = (string) file_get_contents(shippedAgentFiles()['data-investigator']);

    expect($content)->not->toContain('AutoCount')->not->toContain('DMS');
});

it('tells plan-executor never to commit to the integration branch', function () {
    expect((string) file_get_contents(shippedAgentFiles()['plan-executor']))
        ->toContain('never commit to the integration branch');
});
