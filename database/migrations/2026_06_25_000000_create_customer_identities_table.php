<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('external_user_id')->nullable();
            $table->string('external_chat_id')->nullable();
            $table->string('provider_username')->nullable();
            $table->string('phone')->nullable();
            $table->string('normalized_phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(100);
            $table->boolean('is_primary')->default(false);
            $table->json('metadata_json')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'provider'], 'customer_identities_org_provider_index');
            $table->index(['organization_id', 'normalized_phone'], 'customer_identities_org_normalized_phone_index');
            $table->index(['organization_id', 'external_user_id'], 'customer_identities_org_external_user_index');
            $table->index(['organization_id', 'external_chat_id'], 'customer_identities_org_external_chat_index');
            $table->unique(['organization_id', 'provider', 'external_user_id'], 'customer_identities_org_provider_external_user_unique');
            $table->unique(['organization_id', 'provider', 'external_chat_id'], 'customer_identities_org_provider_external_chat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
    }
};
