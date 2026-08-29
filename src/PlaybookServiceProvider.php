<?php

namespace Jiannius\Playbook;

use Illuminate\Support\ServiceProvider;
use Jiannius\Playbook\Console\CheckCommand;

class PlaybookServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings.
     */
    public function register(): void
    {
        //
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
            ]);
        }
    }
}
