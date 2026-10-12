<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('session_data')->index();
            $table->string('source_id', 100)->nullable()->after('source_type')->index();
            $table->text('source_url')->nullable()->after('source_id');
            $table->string('headline')->nullable()->after('source_url');
            $table->text('body')->nullable()->after('headline');
            $table->json('referral_data')->nullable()->after('body');
            $table->timestamp('free_window_expires_at')->nullable()->after('referral_data')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_id',
                'source_url',
                'headline',
                'body',
                'referral_data',
                'free_window_expires_at',
            ]);
        });
    }
};
