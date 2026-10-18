<?php

namespace Jiannius\Playbook;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Jiannius\Playbook\Agents\SyncAfterBoost;
use Jiannius\Playbook\Console\CheckCommand;
use Jiannius\Playbook\Console\SyncCommand;

class PlaybookServiceProvider extends ServiceProvider
{
    /**
     * Fill in the two Boost settings that make a repo's agent files drift when left unset.
     * A value the repo has already set is never touched.
     *
     * Why these two, and why these values: docs/playbook-install.md, under "Changed in Boost 2.10".
     * Checking for null before setting keeps this safe whichever order the providers register in,
     * since Boost's own defaults carry neither key and its mergeConfigFrom keeps values already set.
     */
    public function register(): void
    {
        if (config('boost.agents.claude_code.guidelines_path') === null) {
            config()->set('boost.agents.claude_code.guidelines_path', 'AGENTS.md');
        }

        if (config('boost.enforce_tests') === null) {
            config()->set('boost.enforce_tests', false);
        }

        $this->mergeConfigFrom(dirname(__DIR__).'/config/playbook.php', 'playbook');
    }

    /**
     * Boot package resources into the host application.
     *
     * Guidelines and skills need no registration here — Laravel Boost
     * discovers them by scanning installed packages for
     * resources/boost/guidelines/ and resources/boost/skills/.
     * See Composer::packagesDirectoriesWithBoostSubpath() in laravel/boost.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
                SyncCommand::class,
            ]);

            $this->publishes([
                dirname(__DIR__).'/config/playbook.php' => config_path('playbook.php'),
            ], 'playbook-config');

            Event::listen(CommandFinished::class, [SyncAfterBoost::class, 'handle']);
        }
    }
}
