<?php

declare(strict_types=1);

use Jiannius\Playbook\Agents\AgentCatalog;

/**
 * playbook:sync writes the task agents into .claude/agents/playbook/.
 *
 * The directory is the managed boundary. Everything inside is playbook's and is rewritten to
 * match the package; everything outside belongs to the project and is never written or removed.
 */

/** A project agent file with the given frontmatter name. */
function projectAgent(string $dir, string $relative, string $name): string
{
    $path = $dir.'/.claude/agents/'.$relative;

    is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);
    file_put_contents($path, "---\nname: {$name}\ndescription: Mine.\n---\n\nProject-owned agent {$name}.\n");

    return $path;
}

it('writes all eleven agents, byte for byte, and exits 0', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);

    $shipped = AgentCatalog::shipped();

    expect($shipped)->toHaveCount(11);

    foreach ($shipped as $name => $source) {
        expect(file_get_contents($dir.'/.claude/agents/playbook/'.$name.'.md'))
            ->toBe(file_get_contents($source));
    }
});

it('writes regular files, never symlinks', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);

    foreach (array_keys(AgentCatalog::shipped()) as $name) {
        $path = $dir.'/.claude/agents/playbook/'.$name.'.md';

        expect(is_file($path))->toBeTrue("{$name} is not a regular file");
        expect(is_link($path))->toBeFalse("{$name} is a symlink");
    }
});

it('replaces a symlink at a target path with a regular file', function () {
    $dir = $this->fakeProject(boostJson());
    $source = AgentCatalog::shipped()['debugger'];

    mkdir($dir.'/.claude/agents/playbook', 0755, true);
    symlink($source, $dir.'/.claude/agents/playbook/debugger.md');

    $this->artisan('playbook:sync')->assertExitCode(0);

    $target = $dir.'/.claude/agents/playbook/debugger.md';

    expect(is_link($target))->toBeFalse();
    expect(is_file($target))->toBeTrue();
    expect(file_get_contents($target))->toBe(file_get_contents($source));
});

it('changes nothing on a second run', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);

    $before = collect(glob($dir.'/.claude/agents/playbook/*.md'))
        ->mapWithKeys(fn (string $path): array => [$path => file_get_contents($path)])
        ->all();

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('current');

    $after = collect(glob($dir.'/.claude/agents/playbook/*.md'))
        ->mapWithKeys(fn (string $path): array => [$path => file_get_contents($path)])
        ->all();

    expect($after)->toBe($before);
});

it('overwrites an edited managed file with the source', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);

    file_put_contents($dir.'/.claude/agents/playbook/auditor.md', "Someone edited this.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($dir.'/.claude/agents/playbook/auditor.md'))
        ->toBe(file_get_contents(AgentCatalog::shipped()['auditor']));
});

it('removes a file playbook no longer ships from the managed directory', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);

    file_put_contents($dir.'/.claude/agents/playbook/old-agent.md', "Retired.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/agents/playbook/old-agent.md')->not->toBeFile();
});

it('removes an opted-out agent that was written earlier, and does not write it again', function () {
    $dir = $this->fakeProject(boostJson());

    $this->artisan('playbook:sync')->assertExitCode(0);
    expect($dir.'/.claude/agents/playbook/data-import.md')->toBeFile();

    config(['playbook.agents.except' => ['data-import']]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/agents/playbook/data-import.md')->not->toBeFile();
    expect($dir.'/.claude/agents/playbook/debugger.md')->toBeFile();
});

it('leaves a project override byte-identical when its name is opted out', function () {
    $dir = $this->fakeProject(boostJson());
    $mine = projectAgent($dir, 'data-import.md', 'data-import');
    $before = file_get_contents($mine);

    config(['playbook.agents.except' => ['data-import']]);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('overridden by the project');

    expect(file_get_contents($mine))->toBe($before);
    expect($dir.'/.claude/agents/playbook/data-import.md')->not->toBeFile();
});

it('never modifies a project file outside playbook/, whether or not it clashes', function () {
    $dir = $this->fakeProject(boostJson());
    $unrelated = projectAgent($dir, 'mine.md', 'mine');
    $clashing = projectAgent($dir, 'clash.md', 'debugger');
    $beforeUnrelated = file_get_contents($unrelated);
    $beforeClashing = file_get_contents($clashing);

    $this->artisan('playbook:sync')->assertExitCode(0);
    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($unrelated))->toBe($beforeUnrelated);
    expect(file_get_contents($clashing))->toBe($beforeClashing);
});

it('detects a clash in a nested folder, warns, exits 0 and still writes ours', function () {
    $dir = $this->fakeProject(boostJson());
    projectAgent($dir, 'review/mine.md', 'debugger');

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('the same as a playbook agent');

    expect(file_get_contents($dir.'/.claude/agents/playbook/debugger.md'))
        ->toBe(file_get_contents(AgentCatalog::shipped()['debugger']));
});

it('removes the managed directory when disabled, and leaves project files alone', function () {
    $dir = $this->fakeProject(boostJson());
    $mine = projectAgent($dir, 'mine.md', 'mine');
    $before = file_get_contents($mine);

    $this->artisan('playbook:sync')->assertExitCode(0);
    expect($dir.'/.claude/agents/playbook')->toBeDirectory();

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/agents/playbook')->not->toBeDirectory();
    expect(file_get_contents($mine))->toBe($before);
});

it('writes nothing when Claude Code is not enabled in boost.json', function () {
    $dir = $this->fakeProject(boostJson(['agents' => ['codex']]));

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('skipped');

    expect($dir.'/.claude/agents')->not->toBeDirectory();
});

it('exits 2 when boost is not set up', function () {
    $this->fakeProject(boostJson: null);

    $this->artisan('playbook:sync')->assertExitCode(2);
});

it('warns about an unknown name in except and still exits 0', function () {
    $this->fakeProject(boostJson());

    config(['playbook.agents.except' => ['no-such-agent']]);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('no-such-agent');
});
