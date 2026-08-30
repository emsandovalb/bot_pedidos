<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            // active: el agente responde solo.
            // training: el agente redacta pero no envía (queda como 'draft' en messages).
            // paused: no responde nada.
            $table->string('agent_mode')->default('training')->after('status');

            // Guarda el modo anterior al usar "pausar todo", para poder restaurarlo
            // canal por canal al reactivar en vez de dejarlos todos en 'training'.
            $table->string('agent_mode_before_pause')->nullable()->after('agent_mode');
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropColumn(['agent_mode', 'agent_mode_before_pause']);
        });
    }
};
