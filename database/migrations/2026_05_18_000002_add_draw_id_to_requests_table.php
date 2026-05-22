<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('draw_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('draws')
                ->nullOnDelete();

            $table->index(['organization_id', 'branch_id', 'draw_id', 'status'], 'requests_draw_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('requests_draw_scope_index');
            $table->dropConstrainedForeignId('draw_id');
        });
    }
};
