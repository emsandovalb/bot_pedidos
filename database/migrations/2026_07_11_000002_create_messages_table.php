<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->string('direction'); // inbound|outbound
            $table->string('sender_type'); // customer|ai_agent|human_agent|system

            // Humano que escribió/editó/aprobó el mensaje (si aplica).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // draft: la IA lo redactó y no se envió (modo Entrenamiento).
            // pending_approval: esperando revisión humana explícita.
            // approved / sent / delivered / read / failed / discarded.
            $table->string('status')->default('sent');

            $table->string('content_type')->default('text'); // text|image|audio|video|document|template|location
            $table->text('content')->nullable();

            // Plantillas oficiales de Meta.
            $table->string('template_name')->nullable();
            $table->json('template_params')->nullable();

            $table->string('media_url')->nullable();

            // Idempotencia: Meta/Telegram reenvían webhooks si no confirmas rápido.
            $table->string('provider_message_id')->nullable();
            $table->json('provider_payload')->nullable();

            // Trazabilidad de lo que sugirió la IA vs lo que el humano terminó enviando.
            $table->text('ai_draft_content')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('ai_tokens_input')->nullable();
            $table->unsignedInteger('ai_tokens_output')->nullable();
            $table->decimal('ai_cost', 10, 6)->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Puente al pipeline de pedidos existente: un mensaje de conversación
            // puede ser también el que disparó una captura de pedido.
            $table->foreignId('incoming_message_id')->nullable()
                ->constrained('incoming_messages')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->unique('provider_message_id');
            $table->index(['conversation_id', 'created_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
