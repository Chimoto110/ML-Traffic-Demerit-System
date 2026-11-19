<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshRules extends Command
{
    protected $signature = 'ml:refresh-rules';
    protected $description = 'Re-mine association rules from current transaction data';

    public function handle(): int
    {
        $mlServiceDir = base_path('ml_service');

        $this->info('📋 Re-mining association rules...');
        $this->execShell("python rules/mine_rules.py", $mlServiceDir);

        $this->info('✅ Association rules refreshed.');

        return 0;
    }

    protected function execShell(string $command, string $directory): void
    {
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        $process = proc_open(
            "cd \"{$directory}\" && {$command}",
            $descriptors,
            $pipes
        );

        if (! $process) {
            throw new \RuntimeException("Failed to start command: {$command}");
        }

        proc_close($process);
    }
}
