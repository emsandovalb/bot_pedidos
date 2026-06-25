<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default('draft')->index();
            $table->string('display_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('provider')->nullable();
            $table->string('external_business_id')->nullable();
            $table->string('external_phone_number_id')->nullable();
            $table->string('quality_rating')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'channel'], 'channel_connections_org_channel_unique');
            $table->index(['organization_id', 'status'], 'channel_connections_org_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
