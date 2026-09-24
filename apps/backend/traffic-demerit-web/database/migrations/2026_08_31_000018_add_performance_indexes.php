<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->index(['driver_id', 'occurred_at'], 'idx_violations_driver_occurred_at');
            $table->index(['zone_type', 'weather_conditions'], 'idx_violations_zone_weather');
        });

        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->index(['driver_id', 'created_at'], 'idx_ledger_driver_created_at');
        });

        Schema::table('risk_predictions', function (Blueprint $table) {
            $table->index(['driver_id', 'predicted_at'], 'idx_risk_predictions_driver_predicted_at');
            $table->index(['risk_class', 'predicted_at'], 'idx_risk_predictions_class_predicted_at');
        });

        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->index(['driver_id', 'generated_at'], 'idx_risk_assessments_driver_generated_at');
        });

        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->index(['driver_id', 'status'], 'idx_sanctions_driver_status');
            $table->index(['webhook_status', 'created_at'], 'idx_sanctions_webhook_created_at');
        });

        Schema::table('appeals', function (Blueprint $table) {
            $table->index(['driver_id', 'status'], 'idx_appeals_driver_status');
            $table->index(['resolved_at'], 'idx_appeals_resolved_at');
        });

        Schema::table('review_logs', function (Blueprint $table) {
            $table->index(['sanction_action_id', 'decision'], 'idx_review_logs_sanction_decision');
            $table->index(['reviewed_at'], 'idx_review_logs_reviewed_at');
        });

        Schema::table('system_notifications', function (Blueprint $table) {
            $table->index(['status', 'sent_at'], 'idx_notifications_status_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_status_sent_at');
        });

        Schema::table('review_logs', function (Blueprint $table) {
            $table->dropIndex('idx_review_logs_reviewed_at');
            $table->dropIndex('idx_review_logs_sanction_decision');
        });

        Schema::table('appeals', function (Blueprint $table) {
            $table->dropIndex('idx_appeals_resolved_at');
            $table->dropIndex('idx_appeals_driver_status');
        });

        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->dropIndex('idx_sanctions_webhook_created_at');
            $table->dropIndex('idx_sanctions_driver_status');
        });

        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropIndex('idx_risk_assessments_driver_generated_at');
        });

        Schema::table('risk_predictions', function (Blueprint $table) {
            $table->dropIndex('idx_risk_predictions_class_predicted_at');
            $table->dropIndex('idx_risk_predictions_driver_predicted_at');
        });

        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('idx_ledger_driver_created_at');
        });

        Schema::table('violations', function (Blueprint $table) {
            $table->dropIndex('idx_violations_zone_weather');
            $table->dropIndex('idx_violations_driver_occurred_at');
        });
    }
};
