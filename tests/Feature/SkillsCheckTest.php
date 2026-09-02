<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Jiannius\Playbook\Console\CheckCommand;

/**
 * The skills half of playbook:check.
 *
 * Guidelines reach an agent through CLAUDE.md, which Boost rewrites on every update.
 * Skills reach a teammate through a *committed* directory, and that path has three ways
 * to fail silently: never installed, installed but drifted, or installed into a directory
 * git throws away. All three end with someone doing the work without the standard, and
 * none of them report an error on their own.
 */

/** A boost.json with playbook enabled and skills switched on. */
function skillsBoostJson(array $overrides = []): array
{
    return array_merge([
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['jiannius/playbook'],
        'mcp' => false,
        'skills' => ['jiannius-dev', 'jiannius-qa-tester'],
    ], $overrides);
}

/** Write an agent file so the guidelines half is current and only the skills half is under test. */
function currentGuidelines(string $dir, string $rendered, string $file = 'CLAUDE.md'): void
{
    file_put_contents(
        $dir.'/'.$file,
        "<laravel-boost-guidelines>\n".$rendered."\n\n</laravel-boost-guidelines>\n"
    );
}

function git(string $dir, string $args): void
{
    exec('git -C '.escapeshellarg($dir).' '.$args.' 2>&1');
}

it('reports BROKEN when boost.json has no skills list', function () {
    // The trap: "packages" names the package, so the guidelines arrive and look right,
    // while boost:update installs no skills at all and still reports success.
    $this->fakeProject(skillsBoostJson(['skills' => []]));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('no "skills" list');
});

it('reports STALE when a shipped skill was never installed', function () {
    $dir = $this->fakeProject(skillsBoostJson(), installSkills: false);
    currentGuidelines($dir, $this->renderedGuidelines());

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('is not installed');
});

it('reports STALE when an installed skill has drifted from the packaged source', function () {
    $dir = $this->fakeProject(skillsBoostJson());
    currentGuidelines($dir, $this->renderedGuidelines());

    $this->installSkills($dir, "---\nname: jiannius-dev\n---\n\nSomeone edited the installed copy.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('differs from the packaged source');
});

it('reports BROKEN when the installed skills are gitignored and untracked', function () {
    // This is not hypothetical. It is the state of the first host app we looked at:
    // /.claude in .gitignore, so Boost writes the skills and git throws them away.
    $dir = $this->fakeProject(skillsBoostJson());
    currentGuidelines($dir, $this->renderedGuidelines());

    git($dir, 'init -q');
    file_put_contents($dir.'/.gitignore', "/.claude\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('gitignored and untracked');
});

it('accepts an ignored path whose skills are force-added, because those do reach a clone', function () {
    // Ignored *and* tracked is a repo that force-added the directory. The pattern is
    // irrelevant once a file is tracked, and reporting it would be a false alarm — the
    // kind that teaches people to stop reading the check.
    $dir = $this->fakeProject(skillsBoostJson());
    currentGuidelines($dir, $this->renderedGuidelines());

    git($dir, 'init -q');
    file_put_contents($dir.'/.gitignore', "/.claude\n");
    git($dir, 'add -f .claude');

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports OK when the skills are installed, faithful and reachable', function () {
    $dir = $this->fakeProject(skillsBoostJson());
    currentGuidelines($dir, $this->renderedGuidelines());

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('checks every configured agent at its own skills path', function () {
    // Skills are not a Claude Code feature: in Boost 2.7 all fourteen agents implement
    // SupportsSkills, each with its own directory — .claude/skills, .agents/skills for
    // Codex and OpenCode, .github/skills for Copilot. A repo that enables two agents has
    // two places the skills have to land, and installing one of them is not done.
    $this->fakeProject(skillsBoostJson(['agents' => ['claude_code', 'codex']]));

    $exit = Artisan::call('playbook:check');
    $output = Artisan::output();

    // The fixture installed .claude/skills; Codex's path was never written.
    expect($exit)->toBe(CheckCommand::STALE)
        ->and($output)->toContain('.agents/skills/jiannius-dev/SKILL.md is not installed')
        ->and($output)->not->toContain('.claude/skills/jiannius-dev/SKILL.md is not installed');
});
