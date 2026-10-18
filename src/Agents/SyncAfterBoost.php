<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Agents;

use Illuminate\Console\Events\CommandFinished;
use Throwable;

/**
 * Runs the agent sync when Boost finishes writing a repo's agent files.
 *
 * CommandFinished fires for far more than boost:update. During `composer update` it also
 * fires for `test` (Boost's own `artisan test --list-tests` subprocess) and for
 * `vendor:publish`, so this listener matches the command exactly, acts only on success, is
 * safe to re-enter, and calls the sync service directly. Going through Artisan::call() would
 * start a nested command and fire this event again.
 */
class SyncAfterBoost
{
    private static bool $running = false;

    public function handle(CommandFinished $event): void
    {
        if (! in_array($event->command, ['boost:update', 'boost:install'], true)) {
            return;
        }

        if ($event->exitCode !== 0) {
            return;
        }

        if (self::$running) {
            return;
        }

        self::$running = true;

        try {
            app(AgentSync::class)->run($event->output);
        } catch (Throwable $e) {
            // A failure here must not fail `composer update`: playbook:check in CI reports
            // whatever is missing.
            $event->output->writeln(sprintf(
                '<error>playbook:sync failed: %s. Run php artisan playbook:sync.</error>',
                $e->getMessage()
            ));
        } finally {
            self::$running = false;
        }
    }
}
