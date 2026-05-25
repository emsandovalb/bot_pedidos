<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained('orders')->nullOnDelete();
            $table->json('parser_result_json')->nullable()->after('payload_json');
            $table->decimal('parser_confidence', 5, 2)->nullable()->after('parser_result_json');
            $table->string('parse_status')->nullable()->after('parser_confidence');
            $table->text('status_reason')->nullable()->after('parse_status');
            $table->timestamp('processed_at')->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'parser_result_json',
                'parser_confidence',
                'parse_status',
                'status_reason',
                'processed_at',
            ]);
        });
    }
};
