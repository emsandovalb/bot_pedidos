<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_order_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('closure_date');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('pending_review_count')->default(0);
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->unsignedInteger('preparing_count')->default(0);
            $table->unsignedInteger('ready_for_dispatch_count')->default(0);
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_items', 12, 2)->default(0);
            $table->decimal('total_order_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'closure_date'], 'daily_order_closures_branch_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_order_closures');
    }
};
