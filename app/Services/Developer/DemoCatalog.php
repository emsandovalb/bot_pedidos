<?php

namespace App\Services\Developer;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\ProductTextNormalizer;

/**
 * Siembra (idempotente) el catálogo de demostración definido en
 * `config/pilot.php` -> `demo_products` para una organización.
 *
 * Lo usan el developer toolkit y el simulador de escenarios para que
 * "generar escenario" funcione sobre cualquier base de datos sin depender
 * de que se haya corrido `PilotSeeder` antes: sin catálogo, los pedidos
 * generados quedan `unmatched` y no se pueden confirmar.
 */
class DemoCatalog
{
    public function __construct(private readonly ProductTextNormalizer $normalizer) {}

    public function ensure(Organization $organization): void
    {
        foreach ((array) config('pilot.demo_products', []) as $productData) {
            $product = Product::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'sku' => $productData['sku'],
                ],
                [
                    'branch_id' => null,
                    'name' => $productData['name'],
                    'normalized_name' => $this->normalizer->normalize($productData['name']),
                    'unit_label' => $productData['unit_label'],
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            foreach ((array) ($productData['aliases'] ?? []) as $alias) {
                ProductAlias::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'normalized_alias' => $this->normalizer->normalize($alias),
                    ],
                    [
                        'product_id' => $product->id,
                        'alias' => $alias,
                        'match_weight' => 100,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
