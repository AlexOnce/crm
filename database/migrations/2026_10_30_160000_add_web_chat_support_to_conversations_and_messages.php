<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('channel', 20)->default('whatsapp')->after('contact_name')->index();
            $table->string('visitor_token', 64)->nullable()->unique()->after('channel');
            $table->string('visitor_ip', 45)->nullable()->after('visitor_token');
            $table->text('visitor_user_agent')->nullable()->after('visitor_ip');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('buttons')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['channel', 'visitor_token', 'visitor_ip', 'visitor_user_agent']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('buttons');
        });
    }
};

