<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->foreignId('risk_assessment_id')->nullable()->constrained('risk_assessments')->nullOnDelete();
            $table->string('trigger_reason'); // e.g. "risk_score > 0.65 AND active_points >= 18"
            $table->enum('action_type', ['warning', 'profile_restriction', 'profile_suspension']);
            $table->enum('webhook_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->json('webhook_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_actions');
    }
};
