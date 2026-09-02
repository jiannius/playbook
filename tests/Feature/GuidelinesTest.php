<?php

declare(strict_types=1);

use Jiannius\Playbook\Console\CheckCommand;

/**
 * Test enforcement used to arrive from Boost's own install-time answer, and evaporated on every
 * `composer update` because boost.json never persisted that answer. The mechanism and the version
 * it was observed against are recorded once, in docs/playbook-install.md — not restated here,
 * where nobody would think to update it.
 *
 * Re-homing it here makes it version-controlled and playbook:check-enforced. This test is what
 * stops it going missing a second time.
 */
it('composes the rules that must not silently disappear', function () {
    $this->fakeProject(boostJson());

    expect($this->renderedGuidelines())
        ->toContain('Every change ships with a test')
        ->toContain('Production is off-limits')
        ->toContain('Secrets and customer data')
        ->toContain('CI must stay inside the free allowance');
});

/**
 * The rule is only worth re-homing if the gate actually catches its absence. An agent file
 * carrying every other section but missing this one must still read as stale.
 */
it('reports STALE when an agent file predates the test rule', function () {
    $dir = $this->fakeProject(boostJson());
    $rendered = $this->renderedGuidelines();

    // Positive control first. Without it this test cannot tell "stale because the
    // rule is missing" from "stale for any reason at all" — a change to
    // resolvePath(), to normalise(), or to where Boost writes CLAUDE.md would each
    // produce the exit code below while proving nothing.
    file_put_contents($dir.'/CLAUDE.md', boostBlock($rendered));
    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);

    $withoutTheRule = str_replace(
        'Every change ships with a test',
        'Some heading that is not ours',
        $rendered
    );

    file_put_contents($dir.'/CLAUDE.md', boostBlock($withoutTheRule));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('behind jiannius/playbook');
});
