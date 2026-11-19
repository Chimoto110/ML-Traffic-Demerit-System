<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create k-Means segmentation model and assignment tables.
     */
    public function up(): void
    {
        if (! Schema::hasTable('segment_models')) {
            Schema::create('segment_models', function (Blueprint $table) {
                $table->id();
                $table->string('model_version')->unique(); // e.g. kmeans_v1
                $table->unsignedInteger('k');
                $table->json('centroids'); // centroids in original units
                $table->json('scaler'); // StandardScaler mean/scale
                $table->decimal('wcss', 12, 4);
                $table->decimal('silhouette', 8, 4)->nullable();
                $table->json('elbow_data'); // k -> WCSS for k=1..10
                $table->timestamp('trained_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('driver_segments')) {
            Schema::create('driver_segments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_profile_id')->unique();
                $table->foreign('driver_profile_id')->references('id')->on('driver_profiles')->onDelete('cascade');
                $table->unsignedInteger('segment_id'); // 0..k-1
                $table->string('segment_label');
                $table->decimal('distance_to_centroid', 10, 4);
                $table->string('model_version');
                $table->boolean('is_atypical')->default(false);
                $table->timestamp('assigned_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_segments');
        Schema::dropIfExists('segment_models');
    }
};
