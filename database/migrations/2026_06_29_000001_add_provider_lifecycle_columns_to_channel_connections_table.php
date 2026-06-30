<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->string('provider_version')->nullable()->after('provider');
            $table->string('health_status')->nullable()->after('quality_rating');
            $table->timestamp('health_checked_at')->nullable()->after('health_status');
            $table->text('last_error')->nullable()->after('health_checked_at');
            $table->timestamp('last_ping')->nullable()->after('last_error');
            $table->timestamp('last_message_received_at')->nullable()->after('last_ping');
            $table->timestamp('last_message_sent_at')->nullable()->after('last_message_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropColumn([
                'provider_version',
                'health_status',
                'health_checked_at',
                'last_error',
                'last_ping',
                'last_message_received_at',
                'last_message_sent_at',
            ]);
        });
    }
};
