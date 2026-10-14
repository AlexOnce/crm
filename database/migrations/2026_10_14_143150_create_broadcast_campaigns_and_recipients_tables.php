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
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('channel', 20)->default('sms'); // 'sms', 'whatsapp', 'hybrid'
            $table->string('template_type', 50)->default('flight_delay'); // 'flight_delay', 'flight_cancellation', 'flight_reminder', 'custom'
            $table->text('message');
            $table->json('filters')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('pending')->index(); // 'pending', 'processing', 'completed', 'failed', 'cancelled'
            $table->unsignedSmallInteger('estimated_sms_per_recipient')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('phone', 30)->index();
            $table->string('passenger_name', 150)->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->text('message');
            $table->string('channel_used', 20)->nullable(); // 'sms', 'whatsapp'
            $table->string('status', 20)->default('pending')->index(); // 'pending', 'processing', 'sent', 'failed'
            $table->string('gateway_message_id', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcast_campaigns');
    }
};
