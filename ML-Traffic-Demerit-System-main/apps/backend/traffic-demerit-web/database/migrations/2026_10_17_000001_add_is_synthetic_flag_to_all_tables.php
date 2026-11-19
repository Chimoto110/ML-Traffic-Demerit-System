<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an is_synthetic flag to every entity table so demo/seeded rows
     * can be distinguished from original production data. All changes are
     * additive and reversible.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'roles',
            'driver_profiles',
            'vehicles',
            'stations',
            'officer_assignments',
            'violations',
            'demerit_ledger_entries',
            'payments',
            'sanction_actions',
            'appeals',
            'review_logs',
            'risk_predictions',
            'risk_assessments',
            'system_notifications',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'is_synthetic')) {
                Schema::table($tableName, function (Blueprint $t) use ($tableName) {
                    $t->boolean('is_synthetic')->default(0)->after('updated_at');
                    $t->index('is_synthetic', "{$tableName}_is_synthetic_idx");
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'roles',
            'driver_profiles',
            'vehicles',
            'stations',
            'officer_assignments',
            'violations',
            'demerit_ledger_entries',
            'payments',
            'sanction_actions',
            'appeals',
            'review_logs',
            'risk_predictions',
            'risk_assessments',
            'system_notifications',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'is_synthetic')) {
                Schema::table($tableName, function (Blueprint $t) use ($tableName) {
                    $t->dropIndex("{$tableName}_is_synthetic_idx");
                    $t->dropColumn('is_synthetic');
                });
            }
        }
    }
};
