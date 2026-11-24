<?php

declare(strict_types=1);

use Jiannius\Playbook\Agents\AgentCatalog;
use Jiannius\Playbook\Console\CheckCommand;

/**
 * The always-on rule that tells Claude Code every pull request carries a self-check.
 *
 * The self-check is also written out in the jiannius-dev skill, but a skill only helps when it
 * loads. The rule is loaded in every session, so it travels the same road as the routing rule:
 * written by playbook:sync into a managed directory, and verified by playbook:check.
 */
function selfCheckRulePath(): string
{
    return dirname(__DIR__, 2).'/resources/claude/rules/self-check.md';
}

it('is shipped with the rules', function () {
    expect(AgentCatalog::rules())->toHaveKey('self-check.md');
    expect(AgentCatalog::rules()['self-check.md'])->toBe(selfCheckRulePath());
});

it('names the steps of the self-check', function () {
    $rule = (string) file_get_contents(selfCheckRulePath());

    expect($rule)
        ->toContain('`pr-reviewer`')
        ->toContain('`qa-tester`')
        ->toContain('gh pr checks <n> --watch')
        ->toContain('`## Self-check`')
        ->toContain('PR **description**')
        ->toContain('`jiannius-dev`')
        ->toContain('task agent');
});

it('agrees with step 8 of the jiannius-dev skill', function () {
    $skill = (string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/jiannius-dev/SKILL.md');

    expect($skill)
        ->toContain('`## Self-check`')
        ->toContain('gh pr checks <n> --watch')
        ->toContain('Don\'t write the `Self-check` record while any check is pending')
        ->toContain('the release PR')
        ->toContain('`self-check.md`');
});

it('writes the rule byte for byte, as a regular file', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    $target = $dir.'/.claude/rules/playbook/self-check.md';

    expect(is_file($target))->toBeTrue();
    expect(is_link($target))->toBeFalse();
    expect(file_get_contents($target))->toBe(file_get_contents(selfCheckRulePath()));
});

it('removes the rule when the agents are disabled', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);
    expect($dir.'/.claude/rules/playbook/self-check.md')->toBeFile();

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/rules/playbook')->not->toBeDirectory();
});

it('reports STALE when the rule is missing', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    unlink($dir.'/.claude/rules/playbook/self-check.md');

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('is not installed');
});

it('reports STALE when the rule was edited', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    file_put_contents($dir.'/.claude/rules/playbook/self-check.md', "Someone edited the rule.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('differs from the packaged source');
});
