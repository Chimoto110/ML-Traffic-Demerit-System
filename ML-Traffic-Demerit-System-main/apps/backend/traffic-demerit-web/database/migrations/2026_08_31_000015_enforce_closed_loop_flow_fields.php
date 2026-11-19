<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_logs', function (Blueprint $table) {
            $table->foreignId('reviewed_by_user_id')->nullable()->after('officer_assignment_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('appeals', function (Blueprint $table) {
            $table->string('outcome')->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('reviewed_at');
        });

        DB::table('appeals')
            ->where('status', 'approved')
            ->orWhere('status', 'rejected')
            ->update([
                'outcome' => DB::raw('status'),
                'resolved_at' => DB::raw('COALESCE(reviewed_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('appeals', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'resolved_at']);
        });

        Schema::table('review_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
        });
    }
};
