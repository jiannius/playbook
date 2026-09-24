<?php

declare(strict_types=1);

use Jiannius\Playbook\Console\CheckCommand;

/**
 * Boost 2.10 writes Claude Code's guidelines to AGENTS.md. Claude Code only loads AGENTS.md when
 * there is no CLAUDE.md, so a repo that keeps one must import AGENTS.md from it — otherwise
 * AGENTS.md is current, the staleness check passes, and Claude reads something else entirely.
 *
 * These tests force the 2.10 layout, so they run the same on every Boost the package allows.
 */
function agentsLayout(string $dir, string $rendered, ?string $claudeMd): void
{
    config(['boost.agents.claude_code.guidelines_path' => 'AGENTS.md']);

    file_put_contents($dir.'/AGENTS.md', boostBlock($rendered)."\n## This project\n\nBills in MYR.\n");

    if ($claudeMd !== null) {
        file_put_contents($dir.'/CLAUDE.md', $claudeMd);
    }
}

// Positive control: without it, the BROKEN cases below could fail for any reason at all.
it('reports OK when CLAUDE.md imports AGENTS.md', function () {
    $dir = $this->fakeProject(boostJson());
    agentsLayout($dir, $this->renderedGuidelines(), "@AGENTS.md\n");

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports OK when there is no CLAUDE.md, because Claude Code then loads AGENTS.md itself', function () {
    $dir = $this->fakeProject(boostJson());
    agentsLayout($dir, $this->renderedGuidelines(), null);

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports BROKEN when CLAUDE.md exists without importing AGENTS.md', function () {
    $dir = $this->fakeProject(boostJson());
    agentsLayout($dir, $this->renderedGuidelines(), "# CLAUDE.md\n\nA project constitution and nothing else.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('does not import AGENTS.md');
});

it('reports BROKEN when CLAUDE.md still holds a guidelines block Boost no longer rewrites', function () {
    $dir = $this->fakeProject(boostJson());
    $rendered = $this->renderedGuidelines();
    agentsLayout($dir, $rendered, "@AGENTS.md\n\n".boostBlock($rendered));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('still holds a guidelines block');
});
