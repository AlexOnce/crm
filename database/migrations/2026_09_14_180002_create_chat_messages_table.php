<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->enum('sender', ['user', 'bot', 'agent'])->index();
            $table->foreignId('agent_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('message');
            $table->string('evolution_message_id')->nullable()->index();
            $table->string('media_url')->nullable();
            $table->string('media_type', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
