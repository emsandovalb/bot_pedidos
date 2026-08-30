<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();

            // Denormalizado para poder filtrar/indexar rápido sin join a channel_connections.
            $table->string('channel'); // telegram|whatsapp|instagram|email|voice|web

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_identity_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('open'); // open|pending|snoozed|closed
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Ventana de 24h de WhatsApp — se copia/deriva de customer_identities al abrir el hilo,
            // pero vive aquí también para no tener que joinear en cada render de la bandeja.
            $table->timestamp('service_window_expires_at')->nullable();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->text('ai_summary')->nullable();
            $table->timestamp('ai_summary_updated_at')->nullable();

            $table->unsignedInteger('unread_count')->default(0);

            $table->timestamps();

            $table->index(['organization_id', 'status', 'last_message_at']);
            $table->index(['organization_id', 'channel']);
            $table->index(['channel_connection_id', 'customer_identity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
