<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('avatar')->nullable()->after('email');
            $table->string('provider', 20)->nullable()->after('avatar');
            $table->string('provider_id')->nullable()->after('provider');
            $table->timestamp('first_seen_at')->nullable()->after('remember_token');
            $table->timestamp('last_active_at')->nullable()->after('first_seen_at');
        });

        Schema::table('recordings', function (Blueprint $table) {
            $table->string('anonymous_id', 64)->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['avatar', 'provider', 'provider_id', 'first_seen_at', 'last_active_at']);
        });

        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn('anonymous_id');
        });
    }
};
