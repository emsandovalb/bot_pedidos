<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La conexión de cada organización con su cuenta de Oria: el vínculo en Oria, el último plan conocido y de quién es
 * la cuenta. Oria decide el plan; plan_checked_at dice cuándo se le preguntó por última vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oria_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('oria_link_id');
            $table->string('plan', 16);
            $table->string('account_name')->nullable();
            $table->string('account_email')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('plan_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oria_connections');
    }
};
