<?php

declare(strict_types=1);

/** A boost.json with Boost set up and playbook enabled. */
function guidelinesBoostJson(): array
{
    return [
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['jiannius/playbook'],
        'mcp' => false,
        // Non-empty on purpose: an empty list is its own failure now (boost:update
        // installs no skills at all), and this fixture is about the guidelines half.
        'skills' => ['jiannius-dev', 'jiannius-qa-tester'],
    ];
}

/**
 * Test enforcement used to arrive from Boost's own install-time answer. boost.json does not
 * persist that answer, so every `composer update` ran boost:update and silently deleted the
 * block from the agent files — no error, no diff anyone reviewed, the rule just went away.
 * Observed on the app skeleton against laravel/boost 2.7.0.
 *
 * Re-homing it here makes it version-controlled and playbook:check-enforced. This test is what
 * stops it going missing a second time.
 */
it('composes the rules that must not silently disappear', function () {
    $this->fakeProject(guidelinesBoostJson());

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
    $dir = $this->fakeProject(guidelinesBoostJson());

    $withoutTheRule = str_replace(
        'Every change ships with a test',
        'Some heading that is not ours',
        $this->renderedGuidelines()
    );

    file_put_contents(
        $dir.'/CLAUDE.md',
        "<laravel-boost-guidelines>\n".$withoutTheRule."\n\n</laravel-boost-guidelines>\n"
    );

    $this->artisan('playbook:check')->assertExitCode(1);
});
