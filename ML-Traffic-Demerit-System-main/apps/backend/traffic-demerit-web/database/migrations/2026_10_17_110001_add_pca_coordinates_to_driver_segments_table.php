<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_segments', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_segments', 'pca_x')) {
                $table->double('pca_x')->nullable()->after('distance_to_centroid');
            }
            if (! Schema::hasColumn('driver_segments', 'pca_y')) {
                $table->double('pca_y')->nullable()->after('pca_x');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_segments', function (Blueprint $table) {
            if (Schema::hasColumn('driver_segments', 'pca_x')) {
                $table->dropColumn('pca_x');
            }
            if (Schema::hasColumn('driver_segments', 'pca_y')) {
                $table->dropColumn('pca_y');
            }
        });
    }
};
