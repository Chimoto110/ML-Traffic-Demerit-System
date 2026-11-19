<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Additive enum extension: payments can now be voided (never deleted).
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','completed','failed','voided') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE payments SET status = 'failed' WHERE status = 'voided'");
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending'");
    }
};
