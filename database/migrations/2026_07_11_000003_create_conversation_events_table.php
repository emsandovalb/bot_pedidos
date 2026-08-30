<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // assigned|status_changed|note_added|escalated|
            // channel_mode_changed|tag_added|tag_removed|summary_updated
            $table->string('type');

            $table->string('actor_type')->nullable(); // user|ai|system
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_events');
    }
};
