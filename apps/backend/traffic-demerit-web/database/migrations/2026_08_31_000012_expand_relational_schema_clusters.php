<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->unsignedInteger('permission_level')->default(1);
            $table->text('role_description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->timestamp('registered_on')->nullable()->after('is_active');
        });

        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_name');
            $table->string('region');
            $table->string('physical_address');
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->string('badge_number');
            $table->enum('shift_type', ['day', 'night', 'swing'])->default('day');
            $table->date('assignment_start_date');
            $table->date('assignment_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plate_number')->unique();
            $table->string('make_model');
            $table->string('vehicle_class');
            $table->string('colour')->nullable();
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->timestamps();
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->string('license_class')->nullable()->after('license_no');
            $table->date('license_expiry_date')->nullable()->after('license_issue_date');
            $table->integer('current_demerit_points')->default(0)->after('status');
            $table->enum('account_status', ['active', 'restricted', 'suspended'])->nullable()->after('current_demerit_points');
        });

        Schema::table('violations', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('driver_id')->constrained('vehicles')->nullOnDelete();
            $table->foreignId('officer_assignment_id')->nullable()->after('officer_id')->constrained('officer_assignments')->nullOnDelete();
            $table->decimal('posted_speed_limit', 6, 2)->nullable()->after('speed_recorded');
            $table->string('evidence_reference')->nullable()->after('location');
        });

        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->string('reason_code')->nullable()->after('reason');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('id')->constrained('demerit_ledger_entries')->nullOnDelete();
            $table->decimal('amount_paid', 10, 2)->nullable()->after('amount');
            $table->string('reference_number')->nullable()->after('transaction_ref');
            $table->char('currency', 3)->default('KES')->after('reference_number');
            $table->string('payment_method')->nullable()->after('currency');
        });

        DB::table('payments')->update([
            'amount_paid' => DB::raw('amount'),
            'reference_number' => DB::raw('transaction_ref'),
            'payment_method' => DB::raw('method'),
        ]);

        Schema::create('risk_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->decimal('risk_score', 5, 4);
            $table->enum('risk_class', ['low', 'moderate', 'high']);
            $table->string('model_version');
            $table->decimal('confidence_level', 5, 4)->nullable();
            $table->timestamp('predicted_at');
            $table->timestamps();
        });

        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->foreignId('risk_prediction_id')->nullable()->after('driver_id')->constrained('risk_predictions')->nullOnDelete();
        });

        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->foreignId('risk_prediction_id')->nullable()->after('risk_assessment_id')->constrained('risk_predictions')->nullOnDelete();
            $table->string('sanction_type')->nullable()->after('action_type');
            $table->decimal('fine_amount', 10, 2)->nullable()->after('sanction_type');
            $table->timestamp('effective_from')->nullable()->after('fine_amount');
            $table->timestamp('effective_to')->nullable()->after('effective_from');
            $table->timestamp('issued_at')->nullable()->after('effective_to');
        });

        Schema::table('appeals', function (Blueprint $table) {
            $table->foreignId('sanction_action_id')->nullable()->unique()->after('violation_id')->constrained('sanction_actions')->nullOnDelete();
        });

        Schema::create('review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanction_action_id')->constrained('sanction_actions')->cascadeOnDelete();
            $table->foreignId('officer_assignment_id')->nullable()->constrained('officer_assignments')->nullOnDelete();
            $table->enum('decision', ['upheld', 'amended', 'reversed'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_logs');

        Schema::table('appeals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sanction_action_id');
        });

        Schema::table('sanction_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_prediction_id');
            $table->dropColumn(['sanction_type', 'fine_amount', 'effective_from', 'effective_to', 'issued_at']);
        });

        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_prediction_id');
        });

        Schema::dropIfExists('risk_predictions');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
            $table->dropColumn(['amount_paid', 'reference_number', 'currency', 'payment_method']);
        });

        Schema::table('demerit_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('reason_code');
        });

        Schema::table('violations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropConstrainedForeignId('officer_assignment_id');
            $table->dropColumn(['posted_speed_limit', 'evidence_reference']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['license_class', 'license_expiry_date', 'current_demerit_points', 'account_status']);
        });

        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('officer_assignments');
        Schema::dropIfExists('stations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['is_active', 'registered_on']);
        });

        Schema::dropIfExists('roles');
    }
};
