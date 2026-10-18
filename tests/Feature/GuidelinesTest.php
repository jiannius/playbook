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
        ->toContain('Check GitHub before you start')
        ->toContain('let them decide')
        ->toContain('Find the cause before you change the code')
        ->toContain('Show the evidence before you call it done')
        ->toContain('Plan before a change that spans many files')
        ->toContain('Production is off-limits')
        ->toContain('Never run against, modify, or expose production')
        ->toContain('may also run against **staging**')
        ->toContain('only when you were explicitly given it')
        ->toContain('Changing a running production system is not')
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

/**
 * The staging allowance changed the production rule's wording, so an agent file written before
 * it carries the old rule. That must read as stale, or the repo keeps telling its agents that
 * data work may never leave the local machine while the task agents say otherwise.
 */
it('reports STALE when an agent file predates the staging allowance', function () {
    $dir = $this->fakeProject(boostJson());
    $rendered = $this->renderedGuidelines();

    // Positive control, for the same reason as the test above.
    file_put_contents($dir.'/CLAUDE.md', boostBlock($rendered));
    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);

    $withoutStaging = str_replace('may also run against **staging**', 'run against local only', $rendered);

    expect($withoutStaging)->not->toBe($rendered);

    file_put_contents($dir.'/CLAUDE.md', boostBlock($withoutStaging));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('behind jiannius/playbook');
});
