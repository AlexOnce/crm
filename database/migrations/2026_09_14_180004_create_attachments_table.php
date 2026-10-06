<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('uploaded_by', ['user', 'agent'])->default('user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
