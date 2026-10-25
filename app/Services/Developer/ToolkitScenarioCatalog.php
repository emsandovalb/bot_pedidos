<?php

namespace App\Services\Developer;

/**
 * Datos fijos del toolkit: definiciones de escenarios, casos QA, nombres y
 * textos de pedido de ejemplo.
 */
class ToolkitScenarioCatalog
{
    /**
     * @return array<int, string>
     */
    public function firstNames(): array
    {
        return [
            'Maria',
            'Ana',
            'Carla',
            'Diana',
            'Jose',
            'Luis',
            'Karla',
            'Pablo',
            'Sofia',
            'Sergio',
            'Andrea',
            'Javier',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function lastNames(): array
    {
        return [
            'Lopez',
            'Perez',
            'Vargas',
            'Rojas',
            'Gonzalez',
            'Castro',
            'Morales',
            'Quesada',
            'Soto',
            'Mora',
            'Ramirez',
            'Chaves',
        ];
    }

    public function randomOrderText(string $family, bool $allowUnknown = false): string
    {
        $families = [
            'ferreteria' => [
                '2 bolsas de cemento',
                '3 tubos pvc de 2 pulgadas',
                '5 tornillos galvanizados',
                '4 bloques de concreto',
                '1 cubeta de pintura blanca',
                '2 mangueras de jardin',
            ],
            'supermercado' => [
                '2 kilos tomate',
                '1 lechuga',
                '5 pepinos',
                '2 piñas',
                '1 sandia',
                '3 cebollas',
                '1 caja de huevos',
            ],
            'restaurant' => [
                '2 casados con pollo',
                '1 ceviche mixto',
                '3 refrescos naturales',
                '2 hamburguesas especiales',
                '1 pizza familiar',
                '4 burritos de carne',
            ],
            'feria' => [
                '2 kilos tomate',
                '1 lechuga',
                '5 pepinos',
                '2 piñas',
                '1 sandia',
                '3 cebollas',
                '4 chiles dulces',
            ],
        ];

        $items = $families[$family] ?? $families['ferreteria'];

        if ($allowUnknown && random_int(1, 4) === 1) {
            return '3 cajas de inventario raro';
        }

        return $items[array_rand($items)];
    }

    public function vipOrderText(string $provider, int $index): string
    {
        $catalog = [
            'whatsapp' => [
                '2 bolsas de cemento',
                '1 cubeta de pintura blanca',
                '5 tubos pvc de 2 pulgadas',
                '3 paquetes de tornillos',
            ],
            'telegram' => [
                '2 bolsas de jardin',
                '1 caja de vasos',
                '3 paquetes de servilletas',
                '4 libras de arroz',
            ],
        ];

        $items = $catalog[$provider] ?? $catalog['whatsapp'];

        return $items[$index % count($items)].' urgente';
    }

    public function randomFeriaText(int $index): string
    {
        $phrases = [
            '2 kilos tomate',
            '1 lechuga',
            '5 pepinos',
            '2 piñas',
            '1 sandia',
            '3 cebollas',
            '1 manojo de cilantro',
            '4 chiles dulces',
        ];

        return $phrases[$index % count($phrases)];
    }

    public function randomRestaurantText(int $index): string
    {
        $phrases = [
            '2 casados con pollo',
            '1 ceviche mixto',
            '3 refrescos naturales',
            '2 hamburguesas especiales',
            '1 pizza familiar',
            '4 burritos de carne',
            '2 tacos al pastor',
            '1 orden de papas fritas',
        ];

        return $phrases[$index % count($phrases)];
    }

    public function randomSupermarketText(int $index): string
    {
        $phrases = [
            '2 bolsas de arroz',
            '1 cartucho de huevos',
            '3 panes molidos',
            '2 litros de leche',
            '1 caja de cereal',
            '5 bananos maduros',
            '2 paquetes de servilletas',
        ];

        return $phrases[$index % count($phrases)];
    }

    /**
     * @return array<string, mixed>
     */
    public function scenarioDefinitions(): array
    {
        return [
            'ferreteria_pequena' => [
                'slug' => 'ferreteria_pequena',
                'label' => 'Ferretería pequeña',
                'category' => 'ferreteria',
                'customers' => 15,
                'orders' => 30,
                'provider_mix' => ['whatsapp' => 20, 'telegram' => 10],
                'vip' => 3,
                'duplicates' => 2,
                'spread_days' => 3,
                'estimated_time' => '20-40s',
            ],
            'ferreteria_grande' => [
                'slug' => 'ferreteria_grande',
                'label' => 'Ferreteria grande',
                'category' => 'ferreteria',
                'customers' => 60,
                'orders' => 250,
                'provider_mix' => ['whatsapp' => 160, 'telegram' => 90],
                'vip' => 15,
                'duplicates' => 20,
                'spread_days' => 10,
                'estimated_time' => '2-5m',
            ],
            'tramo_de_feria' => [
                'slug' => 'tramo_de_feria',
                'label' => 'Tramo de feria',
                'category' => 'feria',
                'customers' => 20,
                'orders' => 50,
                'provider_mix' => ['whatsapp' => 30, 'telegram' => 20],
                'vip' => 4,
                'duplicates' => 4,
                'spread_days' => 4,
                'estimated_time' => '30-60s',
            ],
            'mini_supermercado' => [
                'slug' => 'mini_supermercado',
                'label' => 'Mini supermercado',
                'category' => 'supermercado',
                'customers' => 25,
                'orders' => 60,
                'provider_mix' => ['whatsapp' => 40, 'telegram' => 20],
                'vip' => 5,
                'duplicates' => 5,
                'spread_days' => 5,
                'estimated_time' => '40-80s',
            ],
            'distribuidora' => [
                'slug' => 'distribuidora',
                'label' => 'Distribuidora',
                'category' => 'ferreteria',
                'customers' => 40,
                'orders' => 100,
                'provider_mix' => ['whatsapp' => 70, 'telegram' => 30],
                'vip' => 8,
                'duplicates' => 10,
                'spread_days' => 6,
                'estimated_time' => '1-2m',
            ],
            'restaurant' => [
                'slug' => 'restaurant',
                'label' => 'Restaurant',
                'category' => 'restaurant',
                'customers' => 18,
                'orders' => 45,
                'provider_mix' => ['whatsapp' => 30, 'telegram' => 15],
                'vip' => 4,
                'duplicates' => 3,
                'spread_days' => 3,
                'estimated_time' => '25-50s',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function qaDefinitions(): array
    {
        return [
            ['key' => 'duplicate', 'label' => 'Create duplicate scenario', 'description' => 'Repeated demand from the same customer to trigger duplicate detection.'],
            ['key' => 'vip', 'label' => 'Create VIP scenario', 'description' => 'Many orders from one customer to classify it as VIP.'],
            ['key' => 'parser_failures', 'label' => 'Create parser failures', 'description' => 'Malformed or low-confidence payloads for parser QA.'],
            ['key' => 'unknown_products', 'label' => 'Create unknown products', 'description' => 'Products that should miss catalog matching.'],
            ['key' => 'busy_day', 'label' => 'Create busy day', 'description' => 'High-volume mixed provider traffic with natural timing.'],
            ['key' => 'empty_inbox', 'label' => 'Create empty inbox', 'description' => 'Clears demo inbox data for a blank operations view.'],
        ];
    }
}
