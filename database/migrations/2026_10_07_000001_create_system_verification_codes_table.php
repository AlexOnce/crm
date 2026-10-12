<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->index();
            $table->string('service', 50)->default('facebook_page_linking')->index();
            $table->string('sender_phone', 30)->nullable();
            $table->text('full_message');
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'consumed', 'expired'])->default('pending')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_verification_codes');
    }
};
