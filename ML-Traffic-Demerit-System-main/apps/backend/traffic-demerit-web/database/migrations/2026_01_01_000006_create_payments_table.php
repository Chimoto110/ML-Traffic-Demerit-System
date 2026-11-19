<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_id')->constrained('violations')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['mpesa', 'card'])->default('mpesa');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('transaction_ref')->nullable(); // mock STK push checkout id
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
