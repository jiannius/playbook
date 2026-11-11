<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * The qa-tester's browser, as text: the agent declares its own headless Playwright server so the
 * browser ends with the agent, and both the agent and the skill end a run with `browser_close`.
 *
 * Nothing runs these files as code. A model reads them, and a server line dropped from the
 * frontmatter would not fail anywhere except as Chrome windows left open on someone's desktop,
 * so these tests fail here instead.
 */
function qaBrowserSource(string $relative): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relative);
}

/** A shipped file with every run of whitespace collapsed to one space, so rewrapping is not a failure. */
function qaBrowserText(string $relative): string
{
    return (string) preg_replace('/\s+/', ' ', qaBrowserSource($relative));
}

/** @return array<string, mixed> */
function qaTesterFrontmatter(): array
{
    expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', qaBrowserSource('resources/claude/agents/qa-tester.md'), $matches))->toBe(1);

    $parsed = Yaml::parse($matches[1]);

    expect($parsed)->toBeArray();

    return $parsed;
}

it('gives qa-tester its own inline, isolated, headless Playwright server', function () {
    $servers = qaTesterFrontmatter()['mcpServers'] ?? null;

    expect($servers)->toBeArray()->not->toBeEmpty();

    $playwright = collect($servers)->first(fn ($server) => is_array($server) && array_key_exists('playwright', $server));

    expect($playwright)->not->toBeNull('qa-tester declares no inline playwright server');
    expect($playwright['playwright']['type'])->toBe('stdio');
    expect($playwright['playwright']['command'])->toBe('npx');
    expect($playwright['playwright']['args'])
        ->toContain('@playwright/mcp@latest')
        ->toContain('--isolated')
        ->toContain('--headless');
});

it('keeps qa-tester off the Playwright plugin, whose browser is visible and outlives it', function () {
    $denied = (string) (qaTesterFrontmatter()['disallowedTools'] ?? '');

    expect($denied)->toContain('mcp__plugin_playwright_playwright');

    expect(qaBrowserText('resources/claude/agents/qa-tester.md'))
        ->toContain('Use only its tools, `mcp__playwright__*`');
});

it('has the agent and the skill both close the browser as the last browser call', function () {
    foreach ([
        'resources/claude/agents/qa-tester.md',
        'resources/boost/skills/jiannius-qa-tester/SKILL.md',
    ] as $file) {
        expect(qaBrowserText($file))
            ->toContain('make `browser_close` your last browser call, after every screenshot')
            ->toContain('Any browser call after it opens a new one')
            ->toContain('Close each context you open with `newContext()` as soon as you are done with it');
    }
});

it('closes the browser after a before-only run too', function () {
    expect(qaBrowserText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('ends with section 6\'s last step: close the browser');

    expect(qaBrowserText('resources/claude/agents/qa-tester.md'))
        ->toContain('do section 7\'s *Before shots* and nothing else, then close the browser');
});

it('has the routing rule run QA in the main session when a person asks to watch', function () {
    expect(qaBrowserText('resources/claude/rules/task-agents.md'))
        ->toContain('run the `jiannius-qa-tester` skill in this session rather than handing it to `qa-tester`')
        ->toContain('the agent\'s browser is hidden');
});
