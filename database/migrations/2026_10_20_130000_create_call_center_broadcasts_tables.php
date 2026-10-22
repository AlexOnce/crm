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
        Schema::create('call_center_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('source_extension', 30)->default('1');
            $table->text('message');
            $table->string('image_path', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('total_extensions')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('completed')->index(); // 'completed', 'partial', 'failed'
            $table->timestamps();
        });

        Schema::create('call_center_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('call_center_broadcasts')->cascadeOnDelete();
            $table->string('extension', 30)->index();
            $table->string('extension_name', 150)->nullable();
            $table->string('status', 20)->default('sent')->index(); // 'sent', 'failed'
            $table->text('response_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_center_recipients');
        Schema::dropIfExists('call_center_broadcasts');
    }
};
