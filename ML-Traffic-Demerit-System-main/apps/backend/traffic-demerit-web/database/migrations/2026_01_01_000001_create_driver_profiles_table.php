<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('license_no')->unique();
            $table->string('full_name');
            $table->date('license_issue_date');
            // active | restricted | suspended
            $table->enum('status', ['active', 'restricted', 'suspended'])->default('active');
            // Deliberately NO gender / region / occupation columns —
            // excluded per ethical considerations (proposal 3.8.2, algorithmic bias limitation).
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
