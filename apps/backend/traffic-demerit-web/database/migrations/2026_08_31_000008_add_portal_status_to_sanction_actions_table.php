<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->enum('status', ['active', 'lifted'])->default('active')->after('action_type');
            $table->string('lifted_by')->nullable()->after('status');
            $table->timestamp('lifted_at')->nullable()->after('lifted_by');
        });
    }

    public function down(): void
    {
        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->dropColumn(['status', 'lifted_by', 'lifted_at']);
        });
    }
};
