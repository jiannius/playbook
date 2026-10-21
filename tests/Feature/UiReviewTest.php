<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * The UI review loop, as text: qa-tester takes "after" screenshots and ends its report with one
 * fixed line, the routing rule turns that line into one question, and the jiannius-ui-review
 * skill takes the "before" shots and runs the review on the main thread.
 *
 * None of it is code. A model reads each piece on its own and nothing checks that they still agree,
 * so a report line reworded in the agent but not in the rule fails quietly, in someone's session,
 * as a review that is never offered. These tests make it fail here instead.
 *
 * Phrases are matched with whitespace collapsed, so rewrapping a paragraph is not a failure;
 * rewording one is.
 */

/** A shipped file, as written. */
function uiReviewSource(string $relative): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relative);
}

/** A shipped file with every run of whitespace collapsed to one space. */
function uiReviewText(string $relative): string
{
    return (string) preg_replace('/\s+/', ' ', uiReviewSource($relative));
}

/** @return array<string, mixed> */
function uiReviewFrontmatter(string $relative): array
{
    expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', uiReviewSource($relative), $matches))
        ->toBe(1, $relative.' has no frontmatter block');

    $parsed = Yaml::parse($matches[1]);

    expect($parsed)->toBeArray();

    return $parsed;
}

// Phase 1: what qa-tester produces.

it('gives qa-tester and its skill the same report lines', function () {
    foreach ([
        '`UI changes: yes — N after screenshots in <dir>, manifest <file>`',
        '`UI changes: yes — no screenshots (<reason>)`',
        '`UI changes: no`',
        '`Before screenshots: N in <dir>, manifest <file>`',
        '`Before screenshots: none (<reason>)`',
    ] as $line) {
        expect(uiReviewText('resources/claude/agents/qa-tester.md'))->toContain($line);
        expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))->toContain($line);
    }
});

it('keeps UI review screenshots on test data, in one gitignored folder', function () {
    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('## 7. Screenshots for a UI review (Claude Code only)')
        ->toContain('Seeded or local test data only')
        ->toContain('never a local copy of a production database')
        ->toContain('storage/app/ui-review/pr-<N>/r<round>/')
        ->toContain('git check-ignore')
        ->toContain('Do not edit `.gitignore`');

    expect(uiReviewText('resources/claude/agents/qa-tester.md'))
        ->toContain('never real customer data');
});

it('takes every affected screen at desktop and phone width', function () {
    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('**desktop (1440 × 900)**')
        ->toContain('**phone (390 × 844)**');
});

it('takes before shots only when asked, without moving a local branch or migrating', function () {
    // The normal QA run must not pay for a base build nobody may want. And a migration run on the
    // base would have to be rolled back from the base, or the next PR is tested on the wrong schema.
    expect(uiReviewText('resources/claude/agents/qa-tester.md'))
        ->toContain('Take "before" shots only when the task asks for them');

    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('**Before shots** — only when the task asks for them')
        ->toContain('git checkout --detach origin/<base-branch>')
        ->toContain('Do not run migrations here');
});

it('keeps the manifest and the shots consistent from run to run', function () {
    // Each line here is something the dry run on 2026-10-21 got wrong without it.
    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('its desktop and phone shots share the `NN`')
        ->toContain('not the PR\'s title')
        ->toContain('Leave `before_note` `null` when `before` is set')
        ->toContain('`npm run build` is the only npm command in this section')
        ->toContain('never by editing `.env`');
});

it('never lets QA run herd link, secure or unlink', function () {
    // With a stale vendor/, Herd's Boost integration rewrites .claude/skills, AGENTS.md and
    // boost.json. A prompt telling qa-tester not to was not enough; it has to be a standing rule.
    expect(uiReviewText('resources/claude/agents/qa-tester.md'))
        ->toContain('Never run `herd link`, `herd secure` or `herd unlink`');

    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain('Never run `herd link`, `herd secure` or `herd unlink`')
        ->not->toContain('then `herd link` it');
});

it('leaves starting a review to the main session', function () {
    expect(uiReviewText('resources/claude/agents/qa-tester.md'))
        ->toContain("Starting a UI review is the main session's decision, not yours");

    expect(uiReviewText('resources/boost/skills/jiannius-qa-tester/SKILL.md'))
        ->toContain("whether a review starts is the main session's decision");
});
