<?php

namespace Database\Seeders;

use App\Models\ReviewLog;
use App\Models\SanctionAction;
use Illuminate\Database\Seeder;

class ReviewLogSeeder extends Seeder
{
    /**
     * Seed a single review log row for smoke-testing review edit flows.
     */
    public function run(): void
    {
        $sanction = SanctionAction::query()->first();

        if (! $sanction) {
            $this->command?->warn('No sanction_actions rows found; skipping ReviewLogSeeder.');
            return;
        }

        ReviewLog::query()->updateOrCreate(
            [
                'sanction_action_id' => $sanction->id,
            ],
            [
                'officer_assignment_id' => null,
                'decision' => 'upheld',
                'notes' => 'Seeded review log for operations edit smoke test.',
                'reviewed_at' => now(),
            ]
        );
    }
}
