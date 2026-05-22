<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incoming_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_type')->index();
            $table->string('from_identifier');
            $table->string('to_identifier');
            $table->text('raw_text');
            $table->json('payload_json')->nullable();
            $table->string('external_message_id')->nullable()->index();
            $table->string('status')->default('received')->index();
            $table->timestamp('received_at')->index();
            $table->timestamps();

            $table->index(['organization_id', 'branch_id', 'status', 'received_at'], 'incoming_messages_scope_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_messages');
    }
};
