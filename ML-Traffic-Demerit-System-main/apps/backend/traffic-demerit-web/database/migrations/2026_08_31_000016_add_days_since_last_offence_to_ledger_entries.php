<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->integer('days_since_last_offence')->nullable()->after('points_applied');
        });
    }

    public function down(): void
    {
        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('days_since_last_offence');
        });
    }
};
