<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('event_type');
            $table->string('method');
            $table->string('ip')->nullable();
            $table->string('status');
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at');

            $table->index(['provider', 'event_type', 'created_at'], 'webhook_events_provider_event_created_index');
            $table->index(['organization_id', 'provider', 'created_at'], 'webhook_events_org_provider_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
