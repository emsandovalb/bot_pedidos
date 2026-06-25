<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('possible_duplicate_of_order_id')
                ->nullable()
                ->after('incoming_message_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->decimal('duplicate_score', 5, 2)->nullable()->after('possible_duplicate_of_order_id');
            $table->text('duplicate_reason')->nullable()->after('duplicate_score');
            $table->timestamp('duplicate_checked_at')->nullable()->after('duplicate_reason');
            $table->string('order_fingerprint')->nullable()->index()->after('duplicate_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('possible_duplicate_of_order_id');
            $table->dropColumn([
                'duplicate_score',
                'duplicate_reason',
                'duplicate_checked_at',
                'order_fingerprint',
            ]);
        });
    }
};
