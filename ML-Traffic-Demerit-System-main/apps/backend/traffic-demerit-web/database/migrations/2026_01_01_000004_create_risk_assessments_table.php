<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->decimal('risk_score', 5, 4);          // 0.0000 - 1.0000, from /predict
            $table->enum('risk_class', ['low', 'moderate', 'high']);
            $table->string('model_version');
            $table->json('feature_snapshot')->nullable(); // the exact features sent to the ML service
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
