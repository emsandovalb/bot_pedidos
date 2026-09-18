<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('unit_label');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Precio unitario capturado del catálogo al crear el pedido (histórico).
            $table->decimal('unit_price', 10, 2)->nullable()->after('unit');
            $table->decimal('line_total', 10, 2)->nullable()->after('unit_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Suma de las líneas con precio. Null si ninguna línea tiene precio.
            $table->decimal('items_total', 12, 2)->nullable()->after('parsed_payload_json');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('items_total');
        });
    }
};
