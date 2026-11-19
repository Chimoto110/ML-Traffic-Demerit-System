<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rule interpretation columns to association_rules.
     */
    public function up(): void
    {
        if (Schema::hasTable('association_rules')) {
            if (! Schema::hasColumn('association_rules', 'rule_family')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->enum('rule_family', ['risk', 'context'])->nullable()->after('lift');
                });
            }
            if (! Schema::hasColumn('association_rules', 'association_type')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->enum('association_type', ['positive', 'negative', 'independence'])->nullable()->after('rule_family');
                });
            }
            if (! Schema::hasColumn('association_rules', 'composite_score')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->decimal('composite_score', 10, 6)->nullable()->after('association_type');
                });
            }
            if (! Schema::hasColumn('association_rules', 'plain_english')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->text('plain_english')->nullable()->after('composite_score');
                });
            }
        }
    }

    public function down(): void
    {
        // Reversible: drop columns if they exist
        if (Schema::hasTable('association_rules')) {
            if (Schema::hasColumn('association_rules', 'plain_english')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->dropColumn('plain_english');
                });
            }
            if (Schema::hasColumn('association_rules', 'composite_score')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->dropColumn('composite_score');
                });
            }
            if (Schema::hasColumn('association_rules', 'association_type')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->dropColumn('association_type');
                });
            }
            if (Schema::hasColumn('association_rules', 'rule_family')) {
                Schema::table('association_rules', function (Blueprint $table) {
                    $table->dropColumn('rule_family');
                });
            }
        }
    }
};
