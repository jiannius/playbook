<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Jiannius\Playbook\Agents\AgentSync;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The listener that runs playbook:sync after boost:update.
 *
 * CommandFinished is dispatched directly rather than by running a command, so each case
 * names the exact command and exit code under test.
 *
 * There is deliberately no test that runs the real `boost:update` through Artisan. Boost's
 * service provider skips itself under unit tests (BoostServiceProvider::shouldRun() is false
 * when app()->runningUnitTests()), so `boost:update` is not registered here and its
 * container bindings (RuleRepository, ProjectManager, GuidelineConfig) do not exist.
 * Registering the commands by hand fails on the first missing binding, and rebuilding
 * Boost's wiring in Testbench would test the fixture rather than this listener. The
 * dispatch tests below cover the same contract: the event Laravel fires, the exact command
 * names, the exit code, re-entrancy and containment.
 */

/** Dispatch the event Laravel fires when an artisan command ends. */
function finished(string $command, int $exitCode = 0): BufferedOutput
{
    event(new CommandFinished($command, new ArrayInput([]), $output = new BufferedOutput, $exitCode));

    return $output;
}

it('renders the agents when boost:update succeeds', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished('boost:update');

    expect($dir.'/.claude/agents/playbook/debugger.md')->toBeFile();
});

it('renders the agents when boost:install succeeds', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished('boost:install');

    expect($dir.'/.claude/agents/playbook/debugger.md')->toBeFile();
});

it('renders nothing when boost:update fails', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished('boost:update', 1);

    expect($dir.'/.claude/agents')->not->toBeDirectory();
});

it('renders nothing for the test subprocess Boost starts, which fails with exit 1', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished('test', 1);

    expect($dir.'/.claude/agents')->not->toBeDirectory();
});

it('renders nothing for vendor:publish', function () {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished('vendor:publish');

    expect($dir.'/.claude/agents')->not->toBeDirectory();
});

it('renders nothing for a command name that only resembles boost:update', function (string $command) {
    $dir = $this->fakeProject(boostJson(), installAgents: false);

    finished($command);

    expect($dir.'/.claude/agents')->not->toBeDirectory();
})->with(['boost:updater', 'boost:update:extra', '', 'BOOST:UPDATE']);

it('runs the sync once, however many events its own work raises', function () {
    $this->fakeProject(boostJson(), installAgents: false);

    $double = new class extends AgentSync
    {
        public int $runs = 0;

        public function __construct() {}

        public function run(OutputInterface $output): int
        {
            $this->runs++;

            finished('boost:update');

            return 0;
        }
    };

    $this->app->instance(AgentSync::class, $double);

    finished('boost:update');

    expect($double->runs)->toBe(1);
});

it('starts no nested artisan command, so no extra command events fire', function () {
    $this->fakeProject(boostJson(), installAgents: false);

    // Testbench does not forward Symfony's console events to Laravel's dispatcher, so without
    // this a nested Artisan::call() would finish silently and the test could never fail.
    $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();

    $seen = [];

    Event::listen(CommandFinished::class, function (CommandFinished $event) use (&$seen): void {
        $seen[] = $event->command;
    });

    finished('boost:update');

    expect($seen)->toBe(['boost:update']);
});

it('contains a failure in the sync and reports it', function () {
    $this->fakeProject(boostJson(), installAgents: false);

    $this->app->instance(AgentSync::class, new class extends AgentSync
    {
        public function __construct() {}

        public function run(OutputInterface $output): int
        {
            throw new RuntimeException('disk full');
        }
    });

    $output = finished('boost:update');

    expect($output->fetch())->toContain('playbook:sync failed')->toContain('disk full');
});
