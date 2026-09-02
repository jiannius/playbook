<?php

declare(strict_types=1);

use Jiannius\Playbook\Console\CheckCommand;

it('reports BROKEN when boost is not set up', function () {
    $this->fakeProject(boostJson: null);

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('boost:install');
});

it('reports BROKEN when playbook is installed but not enabled in boost.json', function () {
    $this->fakeProject(boostJson(['packages' => []]));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('not enabled in boost.json');
});

it('reports STALE when the agent file has no guidelines block at all', function () {
    $this->fakeProject(boostJson(), claudeMd: "# CLAUDE.md\n\nA project constitution and nothing else.\n");

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::STALE);
});

it('reports STALE when the block exists but predates our guidelines', function () {
    $this->fakeProject(boostJson(), claudeMd: boostBlock("## Laravel\n\nSome older guidance that is not ours."));

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::STALE);
});

it('reports OK when the block carries our guidelines', function () {
    $dir = $this->fakeProject(boostJson());
    $rendered = $this->renderedGuidelines();

    // Guard against a circular test: the rendered text must really be our file.
    expect($rendered)->toContain('Production is off-limits');

    file_put_contents($dir.'/CLAUDE.md', boostBlock($rendered));

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('still reports OK when the project adds its own content outside the block', function () {
    $dir = $this->fakeProject(boostJson());
    $constitution = "\n# Project constitution\n\nThis project bills in MYR.\n";

    file_put_contents($dir.'/CLAUDE.md', boostBlock($this->renderedGuidelines()).$constitution);

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);

    // The command must not have touched the project's own section.
    expect(file_get_contents($dir.'/CLAUDE.md'))->toContain('This project bills in MYR.');
});

it('writes nothing, even when stale', function () {
    $before = "# CLAUDE.md\n\nUntouched.\n";
    $dir = $this->fakeProject(boostJson(), claudeMd: $before);

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::STALE);

    expect(file_get_contents($dir.'/CLAUDE.md'))->toBe($before);
});
