<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrainAll extends Command
{
    protected $signature = 'ml:train-all';
    protected $description = 'Run full ML training pipeline: ETL -> k-means -> rules -> classifier -> reports';

    public function handle(): int
    {
        $mlServiceDir = base_path('ml_service');

        $this->info('🚀 Starting full ML training pipeline...');

        // Step 1: ETL
        $this->info('📦 Step 1/4: ETL - Build features...');
        $this->execShell("python etl/build_features.py", $mlServiceDir);

        // Step 2: k-Means
        $this->info('🎯 Step 2/4: Segmentation - Train k-Means...');
        $this->execShell("python segmentation/train_kmeans.py", $mlServiceDir);

        // Step 3: Rules
        $this->info('📋 Step 3/4: Association rules - Mine rules...');
        $this->execShell("python rules/mine_rules.py", $mlServiceDir);

        // Step 4: Supervised model
        $this->info('🤖 Step 4/4: Supervised model - Train risk classifier...');
        $this->execShell("python train_supervised.py", $mlServiceDir);

        // Step 5: EDA
        $this->info('📊 Step 5/5: EDA - Generate reports...');
        $this->execShell("python etl/eda.py", $mlServiceDir);

        $this->info('✅ ML training pipeline complete!');
        $this->info('   Model artifacts: ml_service/model/');
        $this->info('   Reports: ml_service/reports/');

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
