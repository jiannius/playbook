<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Console;

use Illuminate\Console\Command;
use Jiannius\Playbook\Agents\AgentSync;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('playbook:sync', 'Write this repo\'s Claude Code task agents and rule from jiannius/playbook')]
class SyncCommand extends Command
{
    /** @var string */
    protected $signature = 'playbook:sync';

    public function handle(AgentSync $sync): int
    {
        return $sync->run($this->output);
    }
}
