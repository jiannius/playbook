<?php

declare(strict_types=1);

use Jiannius\Playbook\Agents\AgentCatalog;

/**
 * playbook:sync writes the task agents into .claude/agents/playbook/.
 *
 * The directory is the managed boundary. Everything inside is playbook's and is rewritten to
 * match the package; everything outside belongs to the project and is never written or removed.
 */
it('writes all eleven agents, byte for byte, and exits 0', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    $shipped = AgentCatalog::shipped();

    expect($shipped)->toHaveCount(11);

    foreach ($shipped as $name => $source) {
        expect(file_get_contents($dir.'/.claude/agents/playbook/'.$name.'.md'))
            ->toBe(file_get_contents($source));
    }
});

it('writes regular files, never symlinks', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    foreach (array_keys(AgentCatalog::shipped()) as $name) {
        $path = $dir.'/.claude/agents/playbook/'.$name.'.md';

        expect(is_file($path))->toBeTrue("{$name} is not a regular file");
        expect(is_link($path))->toBeFalse("{$name} is a symlink");
    }
});

it('replaces a symlink at a target path with a regular file', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
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
    $dir = $this->fakeProject(boostJson(), installAgents: false);

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
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    file_put_contents($dir.'/.claude/agents/playbook/auditor.md', "Someone edited this.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($dir.'/.claude/agents/playbook/auditor.md'))
        ->toBe(file_get_contents(AgentCatalog::shipped()['auditor']));
});

it('removes a file playbook no longer ships from the managed directory', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);

    file_put_contents($dir.'/.claude/agents/playbook/old-agent.md', "Retired.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/agents/playbook/old-agent.md')->not->toBeFile();
});

it('removes an opted-out agent that was written earlier, and does not write it again', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(0);
    expect($dir.'/.claude/agents/playbook/data-import.md')->toBeFile();

    config(['playbook.agents.except' => ['data-import']]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect($dir.'/.claude/agents/playbook/data-import.md')->not->toBeFile();
    expect($dir.'/.claude/agents/playbook/debugger.md')->toBeFile();
});

it('leaves a project override byte-identical when its name is opted out', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
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
    $dir = $this->fakeProject(boostJson(), installAgents: false);
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
    $dir = $this->fakeProject(boostJson(), installAgents: false);
    projectAgent($dir, 'review/mine.md', 'debugger');

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('the same as a playbook agent');

    expect(file_get_contents($dir.'/.claude/agents/playbook/debugger.md'))
        ->toBe(file_get_contents(AgentCatalog::shipped()['debugger']));
});

it('removes the managed directory when disabled, and leaves project files alone', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
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
    $dir = $this->fakeProject(boostJson(['agents' => ['codex']]), installAgents: false);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('skipped');

    expect($dir.'/.claude/agents')->not->toBeDirectory();
});

it('exits 2 when boost is not set up', function () {
    $this->fakeProject(boostJson: null, installAgents: false);

    $this->artisan('playbook:sync')->assertExitCode(2);
});

it('warns about an unknown name in except and still exits 0', function () {
    $this->fakeProject(boostJson(), installAgents: false);

    config(['playbook.agents.except' => ['no-such-agent']]);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('no-such-agent');
});

/**
 * A symlinked managed directory is never followed. Following it would write the agents into
 * whatever it points at and prune that directory's other files as if they were playbook's.
 */
it('leaves a symlinked managed directory and its target alone', function (string $managed, bool $enabled) {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
    $outside = outsideDirectory();

    file_put_contents($outside.'/foreign.md', "Not playbook's.\n");
    mkdir(dirname($dir.'/'.$managed), 0755, true);
    symlink($outside, $dir.'/'.$managed);

    config(['playbook.agents.enabled' => $enabled]);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('is a symlink, left alone');

    expect(is_link($dir.'/'.$managed))->toBeTrue()
        ->and(file_get_contents($outside.'/foreign.md'))->toBe("Not playbook's.\n")
        ->and(array_values(array_diff(scandir($outside), ['.', '..'])))->toBe(['foreign.md']);
})->with([
    'agents, enabled' => [AgentCatalog::AGENTS_DIR, true],
    'agents, disabled' => [AgentCatalog::AGENTS_DIR, false],
    'rules, enabled' => [AgentCatalog::RULES_DIR, true],
    'rules, disabled' => [AgentCatalog::RULES_DIR, false],
]);

it('leaves a managed directory alone when a symlinked parent leads outside .claude', function (string $parent, bool $enabled) {
    $dir = $this->fakeProject(boostJson(), installAgents: false);
    $outside = outsideDirectory();

    file_put_contents($outside.'/foreign.md', "Not playbook's.\n");
    is_dir($dir.'/.claude') || mkdir($dir.'/.claude', 0755, true);
    symlink($outside, $dir.'/.claude/'.$parent);

    config(['playbook.agents.enabled' => $enabled]);

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('left alone');

    expect(array_values(array_diff(scandir($outside), ['.', '..'])))->toBe(['foreign.md'])
        ->and(file_get_contents($outside.'/foreign.md'))->toBe("Not playbook's.\n");
})->with([
    'agents, enabled' => ['agents', true],
    'agents, disabled' => ['agents', false],
    'rules, enabled' => ['rules', true],
    'rules, disabled' => ['rules', false],
]);

/**
 * macOS and Windows filesystems are case-insensitive: Debugger.md and debugger.md are one file
 * there. Pruning after the writes deleted what had just been written. On a case-sensitive
 * filesystem the same test checks the stray name is simply removed.
 */
it('replaces a differently-cased managed file instead of writing through it and pruning it', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    mkdir($dir.'/.claude/agents/playbook', 0755, true);
    file_put_contents($dir.'/.claude/agents/playbook/Debugger.md', "Someone's old copy.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);

    $entries = scandir($dir.'/.claude/agents/playbook');

    expect($entries)->toContain('debugger.md')
        ->not->toContain('Debugger.md')
        ->and(file_get_contents($dir.'/.claude/agents/playbook/debugger.md'))
        ->toBe(file_get_contents(AgentCatalog::shipped()['debugger']))
        ->and(array_diff($entries, ['.', '..']))->toHaveCount(11);
});

it('leaves a file in .claude/rules/ outside playbook/ alone', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    mkdir($dir.'/.claude/rules', 0755, true);
    file_put_contents($dir.'/.claude/rules/mine.md', "Our own rule.\n");

    $this->artisan('playbook:sync')->assertExitCode(0);
    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($dir.'/.claude/rules/mine.md'))->toBe("Our own rule.\n");

    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($dir.'/.claude/rules/mine.md'))->toBe("Our own rule.\n")
        ->and($dir.'/.claude/rules/playbook')->not->toBeDirectory();
});

it('warns about a subdirectory inside a managed directory and leaves it alone', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    mkdir($dir.'/.claude/agents/playbook/drafts', 0755, true);
    file_put_contents($dir.'/.claude/agents/playbook/drafts/wip.md', "Work in progress.\n");

    $this->artisan('playbook:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('is a directory inside a playbook-managed directory');

    expect(file_get_contents($dir.'/.claude/agents/playbook/drafts/wip.md'))->toBe("Work in progress.\n")
        ->and($dir.'/.claude/agents/playbook/debugger.md')->toBeFile();

    // And when disabled: the files go, the directory that is not ours stays, so does its parent.
    config(['playbook.agents.enabled' => false]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    expect(file_get_contents($dir.'/.claude/agents/playbook/drafts/wip.md'))->toBe("Work in progress.\n")
        ->and($dir.'/.claude/agents/playbook/debugger.md')->not->toBeFile();
});

it('leaves a dotfile such as .DS_Store inside a managed directory alone', function (bool $enabled) {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    foreach ([AgentCatalog::AGENTS_DIR, AgentCatalog::RULES_DIR] as $managed) {
        mkdir($dir.'/'.$managed, 0755, true);
        file_put_contents($dir.'/'.$managed.'/.DS_Store', 'finder');
    }

    config(['playbook.agents.enabled' => $enabled]);

    $this->artisan('playbook:sync')->assertExitCode(0);

    foreach ([AgentCatalog::AGENTS_DIR, AgentCatalog::RULES_DIR] as $managed) {
        expect(file_get_contents($dir.'/'.$managed.'/.DS_Store'))->toBe('finder');
    }
})->with([true, false]);

/** A directory outside the fixture project, removed after the test. */
function outsideDirectory(): string
{
    $path = sys_get_temp_dir().'/playbook-outside-'.bin2hex(random_bytes(6));
    mkdir($path, 0755, true);

    $GLOBALS['playbook_outside_dirs'][] = $path;

    return $path;
}

afterEach(function () {
    foreach ($GLOBALS['playbook_outside_dirs'] ?? [] as $path) {
        exec('rm -rf '.escapeshellarg($path));
    }

    $GLOBALS['playbook_outside_dirs'] = [];
});
