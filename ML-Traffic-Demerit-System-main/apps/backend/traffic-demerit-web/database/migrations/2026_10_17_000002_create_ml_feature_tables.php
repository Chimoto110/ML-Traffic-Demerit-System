<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create ML feature store tables. Additive only.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ml_driver_features')) {
            Schema::create('ml_driver_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_profile_id')->unique();
                $table->foreign('driver_profile_id')->references('id')->on('driver_profiles')->onDelete('cascade');

                // Core features
                $table->integer('violation_count')->default(0);
                $table->integer('cumulative_active_demerit_points')->default(0);
                $table->integer('days_since_last_offense')->default(365);
                $table->decimal('avg_speed_over_limit', 6, 2)->default(0);
                $table->decimal('share_in_school_zone', 5, 4)->default(0);
                $table->decimal('share_at_night', 5, 4)->default(0);
                $table->decimal('total_fines_kes', 12, 2)->default(0);
                $table->decimal('past_fine_payment_delays_days', 8, 2)->default(0);
                $table->decimal('unpaid_fine_ratio', 5, 4)->default(0);
                $table->integer('appeals_filed')->default(0);

                // Label
                $table->tinyInteger('reoffended_within_180_days')->default(0);
                $table->boolean('new_driver')->default(false);

                $table->boolean('is_synthetic')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ml_transactions')) {
            Schema::create('ml_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('violation_id');
                $table->foreign('violation_id')->references('id')->on('violations')->onDelete('cascade');
                $table->unsignedBigInteger('driver_profile_id');
                $table->foreign('driver_profile_id')->references('id')->on('driver_profiles')->onDelete('cascade');

                // Transaction items (one-hot encoded as booleans)
                $table->boolean('type_speeding')->default(false);
                $table->boolean('type_red_light')->default(false);
                $table->boolean('type_dui')->default(false);
                $table->boolean('type_no_seatbelt')->default(false);
                $table->boolean('type_phone_use')->default(false);
                $table->boolean('type_reckless_driving')->default(false);
                $table->boolean('type_illegal_parking')->default(false);
                $table->boolean('type_driving_without_license')->default(false);
                $table->boolean('type_overloading')->default(false);
                $table->boolean('type_no_insurance')->default(false);

                $table->boolean('zone_urban')->default(false);
                $table->boolean('zone_highway')->default(false);
                $table->boolean('zone_school_zone')->default(false);
                $table->boolean('zone_residential')->default(false);

                $table->boolean('weather_clear')->default(false);
                $table->boolean('weather_rain')->default(false);
                $table->boolean('weather_fog')->default(false);
                $table->boolean('weather_night')->default(false);

                $table->boolean('time_night')->default(false);
                $table->boolean('time_day')->default(false);

                $table->boolean('payment_late')->default(false);
                $table->boolean('payment_completed')->default(false);
                $table->boolean('payment_pending')->default(false);

                $table->boolean('appeal_filed')->default(false);
                $table->boolean('outcome_reoffended_180d')->default(false);

                $table->boolean('is_synthetic')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_transactions');
        Schema::dropIfExists('ml_driver_features');
    }
};
