<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add M-Pesa specific columns to payments table:
     * initiated_at, completed_at, expires_at, failure_code,
     * environment, raw_callback.
     *
     * Also converts status from ENUM to string so we can add 'expired'
     * without breaking old rows.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'initiated_at')) {
                $table->timestamp('initiated_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('initiated_at');
            }
            if (! Schema::hasColumn('payments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('payments', 'failure_code')) {
                $table->string('failure_code')->nullable()->after('failure_reason');
            }
            if (! Schema::hasColumn('payments', 'environment')) {
                $table->string('environment', 20)->nullable()->after('failure_code');
            }
            if (! Schema::hasColumn('payments', 'raw_callback')) {
                $table->longText('raw_callback')->nullable()->after('environment');
            }
        });

        // Convert status ENUM to string so we can add 'expired' and 'initiated'
        // without breaking old rows. Application code validates allowed values.
       DB::statement("ALTER TABLE payments MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_status CHECK (status IN ('pending','completed','failed','voided','expired','initiated'))");
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = ['initiated_at', 'completed_at', 'expires_at', 'failure_code', 'environment', 'raw_callback'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','completed','failed','voided','expired','initiated') NOT NULL DEFAULT 'pending'");
    }
};
