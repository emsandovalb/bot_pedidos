<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->unsignedInteger('match_weight')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'normalized_alias'], 'product_aliases_org_normalized_unique');
            $table->index(['product_id', 'is_active'], 'product_aliases_product_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};
