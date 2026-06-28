<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_identities', function (Blueprint $table) {
            $table->timestamp('last_customer_message_at')->nullable()->after('last_seen_at');
            $table->timestamp('service_window_expires_at')->nullable()->after('last_customer_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_identities', function (Blueprint $table) {
            $table->dropColumn(['last_customer_message_at', 'service_window_expires_at']);
        });
    }
};
