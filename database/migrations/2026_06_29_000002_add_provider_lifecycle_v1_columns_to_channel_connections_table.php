<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->string('version')->nullable()->after('provider');
            $table->string('webhook_status')->nullable()->after('health_status');
            $table->string('credentials_status')->nullable()->after('webhook_status');
            $table->timestamp('last_health_check_at')->nullable()->after('credentials_status');
            $table->timestamp('last_received_message_at')->nullable()->after('last_health_check_at');
            $table->timestamp('last_sent_message_at')->nullable()->after('last_received_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'webhook_status',
                'credentials_status',
                'last_health_check_at',
                'last_received_message_at',
                'last_sent_message_at',
            ]);
        });
    }
};
