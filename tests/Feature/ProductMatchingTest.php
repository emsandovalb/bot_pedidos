<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\ProductMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_matching_service_matches_exact_alias(): void
    {
        [$organization, $product] = $this->makeProductWithAliases([
            'bolsas de jardin',
        ]);

        $result = app(ProductMatchingService::class)->match(
            organization: $organization,
            productName: 'Bolsas de jardin',
            rawText: '2 bolsas de jardin',
        );

        $this->assertSame($product->id, $result['product']?->id);
        $this->assertSame('bolsas de jardin', $result['matched_text']);
        $this->assertGreaterThan(0.9, $result['confidence_score']);
    }

    public function test_product_matching_service_prefers_longest_alias_match(): void
    {
        [$organization, $product] = $this->makeProductWithAliases([
            'vasos',
            'caja de vasos',
        ]);

        $result = app(ProductMatchingService::class)->match(
            organization: $organization,
            productName: 'caja de vasos plasticos',
            rawText: '1 caja de vasos plasticos',
        );

        $this->assertSame($product->id, $result['product']?->id);
        $this->assertSame('caja de vasos', $result['matched_text']);
        $this->assertGreaterThan(0.7, $result['confidence_score']);
    }

    /**
     * @param  array<int, string>  $aliases
     * @return array{0: Organization, 1: Product}
     */
    private function makeProductWithAliases(array $aliases): array
    {
        $organization = Organization::create([
            'name' => 'Matching Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $product = Product::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Demo Product',
            'sku' => 'SKU-900',
            'unit_label' => 'caja',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        foreach ($aliases as $alias) {
            ProductAlias::create([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'alias' => $alias,
                'match_weight' => 100,
                'is_active' => true,
            ]);
        }

        return [$organization, $product];
    }
}
