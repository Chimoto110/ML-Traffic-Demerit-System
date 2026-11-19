<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create association rules table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('association_rules')) {
            Schema::create('association_rules', function (Blueprint $table) {
                $table->id();
                $table->json('antecedent'); // list of item strings
                $table->json('consequent'); // list of item strings
                $table->decimal('support', 10, 6);
                $table->decimal('confidence', 10, 6);
                $table->decimal('coverage', 10, 6);
                $table->decimal('lift', 10, 6);
                $table->decimal('min_support', 10, 6);
                $table->decimal('min_confidence', 10, 6);
                $table->string('model_version'); // e.g. rules_v1
                $table->timestamp('mined_at');
                $table->timestamps();

                $table->index(['model_version', 'mined_at']);
                $table->index('consequent');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('association_rules');
    }
};
