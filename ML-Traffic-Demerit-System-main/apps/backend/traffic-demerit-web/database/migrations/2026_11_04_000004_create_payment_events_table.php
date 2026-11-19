<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create payment_events table — audit trail of every state change on a payment.
     * Records who/what/when/old->new status for compliance and debugging.
     */
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('event_type', 50); // initiated, pending, completed, failed, expired, reconciled
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->string('changed_by', 100)->nullable(); // user_id, 'system', 'callback', 'reconcile'
            $table->string('failure_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('receipt')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
