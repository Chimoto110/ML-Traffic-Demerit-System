<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segment_models', function (Blueprint $table) {
            if (! Schema::hasColumn('segment_models', 'wcss_by_k')) {
                $table->json('wcss_by_k')->nullable()->after('elbow_data');
            }
            if (! Schema::hasColumn('segment_models', 'silhouette_by_k')) {
                $table->json('silhouette_by_k')->nullable()->after('wcss_by_k');
            }
        });
    }

    public function down(): void
    {
        Schema::table('segment_models', function (Blueprint $table) {
            if (Schema::hasColumn('segment_models', 'wcss_by_k')) {
                $table->dropColumn('wcss_by_k');
            }
            if (Schema::hasColumn('segment_models', 'silhouette_by_k')) {
                $table->dropColumn('silhouette_by_k');
            }
        });
    }
};
