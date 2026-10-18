<?php

declare(strict_types=1);

use Jiannius\Playbook\Agents\AgentCatalog;
use Jiannius\Playbook\Console\CheckCommand;

/**
 * The routing rule that tells Claude Code when to hand work to each task agent.
 *
 * It travels the same road as the agents: written by playbook:sync into a managed directory,
 * and verified by playbook:check. A rule naming an agent that is not shipped, or missing one
 * that is, sends work to nothing, so the first test ties the table to the shipped set.
 */
function routingRulePath(): string
{
    return dirname(__DIR__, 2).'/resources/claude/rules/task-agents.md';
}

it('names every shipped agent in its table, and only shipped agents', function () {
    $rule = (string) file_get_contents(routingRulePath());

    preg_match_all('/^\|[^|\n]*\|\s*`([a-z-]+)`\s*\|$/m', $rule, $matches);

    $named = $matches[1];
    $shipped = array_keys(AgentCatalog::shipped());

    expect($named)->not->toBeEmpty();
    expect(array_diff($named, $shipped))->toBe([], 'the rule names an agent playbook does not ship');
    expect(array_diff($shipped, $named))->toBe([], 'a shipped agent is missing from the rule');
});

it('writes the rule byte for byte, as a regular file', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    $target = $dir.'/.claude/rules/playbook/task-agents.md';

    expect(is_file($target))->toBeTrue();
    expect(is_link($target))->toBeFalse();
    expect(file_get_contents($target))->toBe(file_get_contents(routingRulePath()));
});

it('removes the rule when the agents are disabled', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);
    expect($dir.'/.claude/rules/playbook/task-agents.md')->toBeFile();

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/rules/playbook')->not->toBeDirectory();
});

it('reports OK when the rule is installed and current', function () {
    // Positive control for the cases below.
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports STALE when the rule is missing', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    unlink($dir.'/.claude/rules/playbook/task-agents.md');

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('is not installed');
});

it('reports STALE when the rule was edited', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    file_put_contents($dir.'/.claude/rules/playbook/task-agents.md', "Someone edited the rule.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('differs from the packaged source');
});

it('reports STALE when the rule is a symlink', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    $target = $dir.'/.claude/rules/playbook/task-agents.md';

    unlink($target);
    symlink(routingRulePath(), $target);

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('symlink');
});

it('reports BROKEN when .claude/rules is gitignored and untracked', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    agentsGit($dir, 'init -q');
    file_put_contents($dir.'/.gitignore', "/.claude/rules\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('.claude/rules/playbook is gitignored and untracked');
});
