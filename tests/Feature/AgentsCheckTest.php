<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Jiannius\Playbook\Agents\AgentCatalog;
use Jiannius\Playbook\Console\CheckCommand;
use Laravel\Boost\Install\AgentsDetector;

/**
 * The task-agents section of playbook:check.
 *
 * The agents arrive through a committed directory, so they fail the way the skills do:
 * never installed, drifted, or sitting in a directory git throws away. A project agent that
 * shares a name with one of ours is a fourth failure, and the only one that is not a stale
 * copy. Each case starts from a repo where everything else is current, so the exit code
 * can only come from the agents.
 */

/** Make the guidelines half current, so only the agents half is under test. */
function agentsGuidelinesCurrent(string $dir, string $rendered, string $file = 'CLAUDE.md'): void
{
    file_put_contents($dir.'/'.$file, boostBlock($rendered));
}

function agentsGit(string $dir, string $args): void
{
    exec('git -C '.escapeshellarg($dir).' '.$args.' 2>&1');
}

/** A project agent file with the given frontmatter name. */
function projectOwnedAgent(string $dir, string $relative, string $name): string
{
    $path = $dir.'/.claude/agents/'.$relative;

    is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);
    file_put_contents($path, "---\nname: {$name}\ndescription: Mine.\n---\n\nProject-owned agent {$name}.\n");

    return $path;
}

it('reports OK when every agent is current', function () {
    // Positive control. Every case below asserts a non-zero exit, and without this one a
    // fixture that was broken for some other reason would pass them all.
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports STALE when an agent was never installed', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    unlink($dir.'/.claude/agents/playbook/debugger.md');

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('is not installed');
});

it('reports STALE when an installed agent has drifted from the packaged source', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    file_put_contents($dir.'/.claude/agents/playbook/debugger.md', "Someone edited the installed copy.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('differs from the packaged source');
});

it('reports STALE when a managed agent is a symlink, even to the right bytes', function () {
    // Claude Code 2.1.301 does not load a symlinked project file, so a symlink that
    // resolves to the correct content is still an agent that is not there.
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    $target = $dir.'/.claude/agents/playbook/debugger.md';

    unlink($target);
    symlink(AgentCatalog::shipped()['debugger'], $target);

    expect(file_get_contents($target))->toBe(file_get_contents(AgentCatalog::shipped()['debugger']));

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('symlink');
});

it('reports STALE for a file in the managed directory that playbook no longer ships', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    file_put_contents($dir.'/.claude/agents/playbook/old-agent.md', "Retired.\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::STALE)
        ->expectsOutputToContain('old-agent.md');
});

it('reports STALE for an opted-out agent still installed, and OK once it is removed', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    config(['playbook.agents.except' => ['data-import']]);

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::STALE);

    unlink($dir.'/.claude/agents/playbook/data-import.md');

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('reports BROKEN when a project agent has the same name as one of ours', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    projectOwnedAgent($dir, 'mine.md', 'debugger');

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('config/playbook.php');
});

it('reports OK when the clashing name is opted out, and says the project overrides it', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    projectOwnedAgent($dir, 'mine.md', 'debugger');
    unlink($dir.'/.claude/agents/playbook/debugger.md');

    config(['playbook.agents.except' => ['debugger']]);

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::OK)
        ->expectsOutputToContain('overridden by the project');
});

it('reports BROKEN when the agents are gitignored and untracked', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    agentsGit($dir, 'init -q');
    file_put_contents($dir.'/.gitignore', "/.claude\n");

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::BROKEN)
        ->expectsOutputToContain('gitignored and untracked');
});

it('accepts ignored agents that were force-added, because those do reach a clone', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    agentsGit($dir, 'init -q');
    file_put_contents($dir.'/.gitignore', "/.claude\n");
    agentsGit($dir, 'add -f .claude');

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::OK);
});

it('skips the agents section when Claude Code is not enabled', function () {
    $dir = $this->fakeProject(boostJson(['agents' => ['codex']]), installAgents: false);

    // Codex reads AGENTS.md and keeps its skills in .agents/skills, so make both current
    // and leave the agents section as the only thing that could change the exit code.
    foreach (shippedAgentSkillSources() as $name => $source) {
        mkdir($dir.'/.agents/skills/'.$name, 0755, true);
        copy($source, $dir.'/.agents/skills/'.$name.'/SKILL.md');
    }

    $guidelines = $this->renderedGuidelines();
    $codex = collect($this->app->make(AgentsDetector::class)->getAgents())
        ->first(fn ($agent): bool => $agent->name() === 'codex');

    agentsGuidelinesCurrent($dir, $codex->transformGuidelines($guidelines), 'AGENTS.md');

    $exit = Artisan::call('playbook:check');
    $output = Artisan::output();

    expect($output)->toContain('skipped')
        ->and($exit)->toBe(CheckCommand::OK);
});

it('reports STALE when disabled but managed files are still installed', function () {
    $dir = $this->fakeProject(boostJson());
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:check')->assertExitCode(CheckCommand::STALE);
});

it('reports OK and says disabled when disabled and nothing is installed', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
    agentsGuidelinesCurrent($dir, $this->renderedGuidelines());

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:check')
        ->assertExitCode(CheckCommand::OK)
        ->expectsOutputToContain('disabled');
});

/** @return array<string, string> skill name => absolute path to its SKILL.md */
function shippedAgentSkillSources(): array
{
    $sources = [];

    foreach (glob(dirname(__DIR__, 2).'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $sources[basename($dir)] = $dir.'/SKILL.md';
    }

    return $sources;
}
