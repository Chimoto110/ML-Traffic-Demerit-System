<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AssignSegments extends Command
{
    protected $signature = 'ml:assign-segments';
    protected $description = 'Re-assign all drivers to k-Means segments using the latest model';

    public function handle(): int
    {
        $mlServiceDir = base_path('ml_service');

        $this->info('🎯 Re-assigning driver segments...');
        $this->execShell("python segmentation/train_kmeans.py", $mlServiceDir);

        $this->info('✅ Segment assignments updated.');

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
