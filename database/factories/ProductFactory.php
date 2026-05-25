<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'organization_id' => Organization::query()->create([
                'name' => fake()->company(),
                'status' => Organization::STATUS_ACTIVE,
            ])->id,
            'branch_id' => fn (array $attributes) => Branch::query()->create([
                'organization_id' => $attributes['organization_id'],
                'name' => fake()->company() . ' Sucursal',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => fake()->unique()->bothify('branch-####'),
                'status' => Branch::STATUS_ACTIVE,
            ])->id,
            'name' => $name,
            'normalized_name' => Str::of($name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString(),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'unit_label' => fake()->optional()->randomElement(['pieza', 'bolsa', 'caja', 'paquete']),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
