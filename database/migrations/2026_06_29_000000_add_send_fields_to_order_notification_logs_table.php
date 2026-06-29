<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_notification_logs', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('provider');
            $table->timestamp('sent_at')->nullable()->after('provider_message_id');
            $table->text('error_message')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_notification_logs', function (Blueprint $table) {
            $table->dropColumn(['provider_message_id', 'sent_at', 'error_message']);
        });
    }
};
