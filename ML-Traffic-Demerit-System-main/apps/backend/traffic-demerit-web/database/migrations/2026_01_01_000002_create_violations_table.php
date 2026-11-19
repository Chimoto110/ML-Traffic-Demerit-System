<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('offense_type');           // e.g. speeding, dui, illegal_parking
            $table->decimal('speed_recorded', 6, 2)->nullable();
            $table->enum('zone_type', ['urban', 'highway', 'school_zone', 'residential']);
            $table->enum('weather_conditions', ['clear', 'rain', 'fog', 'night']);
            $table->string('location')->nullable();
            $table->unsignedTinyInteger('points_assigned');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
