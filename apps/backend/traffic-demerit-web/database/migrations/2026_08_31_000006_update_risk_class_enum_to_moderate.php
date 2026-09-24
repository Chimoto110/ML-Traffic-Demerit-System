<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE risk_assessments MODIFY risk_class ENUM('low','medium','moderate','high') NOT NULL");
        DB::statement("UPDATE risk_assessments SET risk_class='moderate' WHERE risk_class='medium'");
        DB::statement("ALTER TABLE risk_assessments MODIFY risk_class ENUM('low','moderate','high') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE risk_assessments MODIFY risk_class ENUM('low','medium','moderate','high') NOT NULL");
        DB::statement("UPDATE risk_assessments SET risk_class='medium' WHERE risk_class='moderate'");
        DB::statement("ALTER TABLE risk_assessments MODIFY risk_class ENUM('low','medium','high') NOT NULL");
    }
};
