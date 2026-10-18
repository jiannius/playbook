<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * The reusable `@claude` workflow runs on a shared CI seat.
 *
 * A repo's .claude/agents/ pin their own models, so a mention that could start a task agent
 * could start an Opus agent on that seat. The reply step switches the Agent tool off, and this
 * test is what stops that line being dropped in a later edit.
 */

/** @return array<string, mixed> the step with this name, from whichever job holds it */
function workflowStep(string $name): array
{
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/claude.yml');

    foreach ($workflow['jobs'] as $job) {
        foreach ($job['steps'] ?? [] as $step) {
            if (($step['name'] ?? null) === $name) {
                return $step;
            }
        }
    }

    throw new RuntimeException("claude.yml has no step named [{$name}]");
}

it('keeps the review step read-only apart from inline comments', function () {
    // Positive control: proves the parsing finds the step and its arguments, so the
    // assertion below is not passing against an empty string.
    expect(workflowStep('Review the pull request')['with']['claude_args'])
        ->toContain('--allowedTools "mcp__github_inline_comment__create_inline_comment"');
});

it('switches the Agent tool off when replying to a mention', function () {
    expect(workflowStep('Reply to a mention')['with']['claude_args'])
        ->toContain('--disallowedTools "Agent"');
});
