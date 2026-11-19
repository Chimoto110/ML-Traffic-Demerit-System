<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'card_brand')) {
                $table->string('card_brand')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('payments', 'card_last4')) {
                $table->string('card_last4', 4)->nullable()->after('card_brand');
            }
            if (! Schema::hasColumn('payments', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('card_last4');
            }
            if (! Schema::hasColumn('payments', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('provider_reference');
            }
            if (! Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('failure_reason');
            }
            if (! Schema::hasColumn('payments', 'merchant_request_id')) {
                $table->string('merchant_request_id')->nullable()->after('idempotency_key');
            }
            if (! Schema::hasColumn('payments', 'checkout_request_id')) {
                $table->string('checkout_request_id')->nullable()->after('merchant_request_id');
            }
            if (! Schema::hasColumn('payments', 'mpesa_receipt')) {
                $table->string('mpesa_receipt')->nullable()->after('checkout_request_id');
            }
            if (! Schema::hasColumn('payments', 'result_code')) {
                $table->string('result_code')->nullable()->after('mpesa_receipt');
            }
            if (! Schema::hasColumn('payments', 'result_desc')) {
                $table->string('result_desc')->nullable()->after('result_code');
            }
            if (! Schema::hasColumn('payments', 'phone')) {
                $table->string('phone')->nullable()->after('result_desc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = [
                'card_brand', 'card_last4', 'provider_reference', 'failure_reason',
                'idempotency_key', 'merchant_request_id', 'checkout_request_id',
                'mpesa_receipt', 'result_code', 'result_desc', 'phone',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    if ($column === 'idempotency_key') {
                        $table->dropUnique('payments_idempotency_key_unique');
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
