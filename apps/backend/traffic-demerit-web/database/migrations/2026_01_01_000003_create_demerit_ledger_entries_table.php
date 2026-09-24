<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demerit_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->foreignId('violation_id')->nullable()->constrained('violations')->nullOnDelete();
            $table->integer('points_applied');   // can be negative for point decay/expiry
            $table->integer('running_balance');  // snapshot balance after this entry
            $table->string('reason')->nullable(); // e.g. "violation", "annual_decay", "manual_adjustment"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demerit_ledger_entries');
    }
};
