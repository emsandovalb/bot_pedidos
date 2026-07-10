<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();

            $table->date('requested_date')->nullable();
            $table->string('requested_time_window')->nullable();

            $table->string('delivery_method')->nullable();
            $table->string('payment_method')->nullable();

            $table->foreignId('pickup_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('delivery_address')->nullable();
            $table->text('delivery_notes')->nullable();

            $table->integer('priority_score')->default(0);
            $table->string('priority_level')->nullable();
            $table->text('priority_reason')->nullable();

            $table->date('commitment_date')->nullable();
            $table->time('commitment_time')->nullable();
            $table->integer('sla_minutes')->nullable();

            $table->integer('planner_confidence')->default(0);
            $table->text('planner_notes')->nullable();
            $table->json('metadata_json');
            $table->timestamps();

            $table->index(['organization_id', 'order_id'], 'fulfillment_plans_org_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_plans');
    }
};
