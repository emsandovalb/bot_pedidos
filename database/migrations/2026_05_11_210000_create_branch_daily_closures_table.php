<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_daily_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('closure_date');
            $table->unsignedInteger('total_requests');
            $table->unsignedInteger('total_confirmed');
            $table->unsignedInteger('total_rejected');
            $table->unsignedInteger('total_pending');
            $table->decimal('total_amount_confirmed', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['branch_id', 'closure_date']);
            $table->index(['organization_id', 'branch_id', 'closure_date'], 'branch_daily_closures_scope_index');
            $table->index(['organization_id', 'closed_by'], 'branch_daily_closures_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_daily_closures');
    }
};
