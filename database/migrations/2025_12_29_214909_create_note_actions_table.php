<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['task', 'meeting', 'reminder']);
            $table->json('source_items');
            $table->json('payload');
            $table->enum('status', ['open', 'done', 'cancelled'])->default('open');
            $table->timestamps();

            $table->index(['recording_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_actions');
    }
};
