<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->longText('transcript')->nullable()->after('duration_seconds');
            $table->string('ai_title')->nullable()->after('transcript');
            $table->longText('ai_summary')->nullable()->after('ai_title');
            $table->json('ai_action_items')->nullable()->after('ai_summary');
            $table->json('ai_meta')->nullable()->after('ai_action_items');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['transcript', 'ai_title', 'ai_summary', 'ai_action_items', 'ai_meta']);
        });
    }
};
