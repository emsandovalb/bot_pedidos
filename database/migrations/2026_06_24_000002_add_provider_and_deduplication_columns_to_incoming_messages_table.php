<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('channel_type')->index();
        });

        DB::table('incoming_messages')
            ->whereNull('provider')
            ->update([
                'provider' => DB::raw('channel_type'),
            ]);

        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->unique(['organization_id', 'provider', 'external_message_id'], 'incoming_messages_provider_external_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->dropUnique('incoming_messages_provider_external_message_unique');
            $table->dropColumn('provider');
        });
    }
};
