<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();

            // chat|embeddings|classifier|transcription|moderation
            $table->string('agent_type');
            $table->string('channel')->nullable();
            $table->string('model');

            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->decimal('cost', 10, 6)->default(0);

            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'agent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usages');
    }
};
