<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert payments.method and payments.payment_method from enum to string.
     * The enum only allowed 'mpesa' and 'card', but config/payments.php also
     * registers 'airtel_money' and 'equity'. Converting to string avoids
     * "Data truncated" errors when those methods are used.
     * Additive, reversible: existing rows keep their values.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method')->nullable()->change();
            $table->string('payment_method')->nullable()->change();
        });

        // Backfill any NULLs (shouldn't exist, but be safe)
        DB::table('payments')->whereNull('method')->update(['method' => 'mpesa']);
        DB::table('payments')->whereNull('payment_method')->update(['payment_method' => 'mpesa']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['mpesa', 'card'])->nullable()->change();
            $table->enum('payment_method', ['mpesa', 'card'])->nullable()->change();
        });
    }
};
