<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('title', 150);
            $table->string('category', 60)->index();
            $table->string('description', 255)->nullable();
            $table->longText('message');
            $table->longText('default_message');
            $table->json('variables')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_messages');
    }
};
