<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Tests\Feature;

use Illuminate\Contracts\Foundation\Application;
use Jiannius\Playbook\Tests\TestCase;

/**
 * Same app, but the repo's own config/boost.php has already set both keys by the time the
 * package registers. A real app loads its config files before any provider registers; Testbench's
 * defineEnvironment runs after providers do, so it would only prove the repo can overwrite the
 * default. resolveApplicationConfiguration is the hook that runs before registration.
 */
class BoostOverridesTest extends TestCase
{
    /** @param  Application  $app */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('boost.agents.claude_code.guidelines_path', 'CLAUDE.md');
        $app['config']->set('boost.enforce_tests', true);
    }

    public function test_values_the_repo_sets_win(): void
    {
        $this->assertSame('CLAUDE.md', config('boost.agents.claude_code.guidelines_path'));
        $this->assertTrue(config('boost.enforce_tests'));
    }
}
