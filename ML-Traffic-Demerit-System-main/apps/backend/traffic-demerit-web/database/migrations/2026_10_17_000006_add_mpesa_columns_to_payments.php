<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add M-Pesa STK Push columns to payments table.
     * Additive only.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'merchant_request_id')) {
                $table->string('merchant_request_id')->nullable();
            }
            if (! Schema::hasColumn('payments', 'checkout_request_id')) {
                $table->string('checkout_request_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('payments', 'mpesa_receipt')) {
                $table->string('mpesa_receipt')->nullable();
            }
            if (! Schema::hasColumn('payments', 'result_code')) {
                $table->string('result_code')->nullable();
            }
            if (! Schema::hasColumn('payments', 'result_desc')) {
                $table->string('result_desc')->nullable();
            }
            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'merchant_request_id')) {
                    $table->dropColumn('merchant_request_id');
                }
                if (Schema::hasColumn('payments', 'checkout_request_id')) {
                    $table->dropUnique('payments_checkout_request_id_unique');
                    $table->dropColumn('checkout_request_id');
                }
                if (Schema::hasColumn('payments', 'mpesa_receipt')) {
                    $table->dropColumn('mpesa_receipt');
                }
                if (Schema::hasColumn('payments', 'result_code')) {
                    $table->dropColumn('result_code');
                }
                if (Schema::hasColumn('payments', 'result_desc')) {
                    $table->dropColumn('result_desc');
                }
            });
        }
    }
};
