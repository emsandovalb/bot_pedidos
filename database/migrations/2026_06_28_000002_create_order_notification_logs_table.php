<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('event');
            $table->string('status');
            $table->boolean('should_send');
            $table->boolean('requires_template');
            $table->longText('message_body')->nullable();
            $table->text('reason')->nullable();
            $table->string('provider')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['organization_id', 'order_id'], 'order_notification_logs_org_order_index');
            $table->index(['order_id', 'evaluated_at'], 'order_notification_logs_order_evaluated_index');
            $table->index(['organization_id', 'channel', 'event'], 'order_notification_logs_org_channel_event_index');
            $table->index(['status', 'evaluated_at'], 'order_notification_logs_status_evaluated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notification_logs');
    }
};
