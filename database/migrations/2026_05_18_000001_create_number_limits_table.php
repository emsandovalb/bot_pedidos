<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draw_id')->constrained('draws')->cascadeOnDelete();
            $table->string('number', 2);
            $table->decimal('max_amount', 12, 2);
            $table->timestamps();

            $table->unique(['organization_id', 'branch_id', 'draw_id', 'number'], 'number_limits_unique_scope');
            $table->index(['organization_id', 'branch_id', 'draw_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_limits');
    }
};
