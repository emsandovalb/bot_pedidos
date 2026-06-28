<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('event');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_open_service_window')->default(false);
            $table->boolean('use_template_if_window_closed')->default(false);
            $table->string('template_name')->nullable();
            $table->text('message_body')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'channel', 'event'], 'notification_settings_org_channel_event_unique');
            $table->index(['organization_id', 'channel'], 'notification_settings_org_channel_index');
            $table->index(['organization_id', 'event'], 'notification_settings_org_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
