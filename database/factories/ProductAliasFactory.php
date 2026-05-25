<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAlias>
 */
class ProductAliasFactory extends Factory
{
    protected $model = ProductAlias::class;

    public function definition(): array
    {
        $alias = fake()->unique()->words(2, true);
        $product = Product::factory()->create();

        return [
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'alias' => $alias,
            'normalized_alias' => Str::of($alias)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString(),
            'match_weight' => 100,
            'is_active' => true,
        ];
    }
}
