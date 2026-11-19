<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create mpesa_callbacks table — audit log of every callback ever received
     * from Daraja. Never delete these rows.
     */
    public function up(): void
    {
        Schema::create('mpesa_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable();
            $table->longText('payload');
            $table->string('source_ip', 45)->nullable();
            $table->boolean('signature_ok')->default(false);
            $table->boolean('ip_ok')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result', 50)->nullable();
            $table->timestamps();

            $table->index(['checkout_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_callbacks');
    }
};
