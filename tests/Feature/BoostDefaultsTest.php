<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Tests\Feature;

use Jiannius\Playbook\Tests\TestCase;
use Laravel\Boost\Install\Agents\ClaudeCode;

/**
 * The package fills in the two Boost settings that otherwise make a repo's agent files drift,
 * and why is recorded once in docs/playbook-install.md. A value the repo sets always wins.
 */
class BoostDefaultsTest extends TestCase
{
    public function test_it_sets_both_defaults_when_the_repo_sets_neither(): void
    {
        $this->assertSame('AGENTS.md', config('boost.agents.claude_code.guidelines_path'));
        $this->assertFalse(config('boost.enforce_tests'));
    }

    public function test_claude_code_resolves_to_agents_md_even_when_claude_md_exists(): void
    {
        // The Boost 2.10.1+ fallback this guards against: with no path configured,
        // ClaudeCode::guidelinesPath() returns CLAUDE.md whenever that file exists.
        $dir = sys_get_temp_dir().'/playbook-test-'.bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir.'/CLAUDE.md', "@AGENTS.md\n");

        try {
            $this->app->setBasePath($dir);

            $this->assertFileExists(base_path('CLAUDE.md'));
            $this->assertSame('AGENTS.md', $this->app->make(ClaudeCode::class)->guidelinesPath());
        } finally {
            exec('rm -rf '.escapeshellarg($dir));
        }
    }
}
