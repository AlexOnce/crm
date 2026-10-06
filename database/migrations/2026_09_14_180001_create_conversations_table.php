<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30)->unique()->index();
            $table->string('contact_name')->nullable();
            $table->enum('status', ['bot_active', 'waiting_agent', 'human_live', 'closed'])
                ->default('bot_active')
                ->index();
            $table->string('current_state', 50)->default('MENU_INICIAL')->index();
            $table->json('session_data')->nullable();
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('last_interaction_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
