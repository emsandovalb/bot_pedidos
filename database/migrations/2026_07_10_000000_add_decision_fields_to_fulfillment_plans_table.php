<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_plans', function (Blueprint $table): void {
            $table->string('risk_level')->nullable()->after('sla_minutes');
            $table->text('risk_reason')->nullable()->after('risk_level');
            $table->integer('remaining_sla_minutes')->nullable()->after('risk_reason');
            $table->string('decision_version')->default('v1')->after('remaining_sla_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'risk_level',
                'risk_reason',
                'remaining_sla_minutes',
                'decision_version',
            ]);
        });
    }
};
