<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_message_id')->nullable()->constrained('incoming_messages')->nullOnDelete();
            $table->string('source_channel')->default('telegram');
            $table->string('external_message_id')->nullable();
            $table->string('status')->default('pending_review');
            $table->decimal('parser_confidence', 5, 2)->nullable();
            $table->text('raw_message_text')->nullable();
            $table->json('parsed_payload_json')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_for_dispatch_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'orders_org_status_index');
            $table->index(['branch_id', 'status'], 'orders_branch_status_index');
            $table->index(['customer_id', 'status'], 'orders_customer_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
