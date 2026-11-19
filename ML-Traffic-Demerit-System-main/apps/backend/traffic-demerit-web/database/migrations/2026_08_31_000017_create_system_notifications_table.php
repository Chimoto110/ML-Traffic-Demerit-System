<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_role')->nullable();
            $table->string('channel')->default('in_app');
            $table->string('title');
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->enum('status', ['unread', 'read'])->default('unread');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'status']);
            $table->index(['recipient_role', 'status']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
