<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $order = Order::factory()->create();
        $name = fake()->words(2, true);
        $product = Product::query()->create([
            'organization_id' => $order->organization_id,
            'branch_id' => $order->branch_id,
            'name' => $name,
            'normalized_name' => Str::of($name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString(),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'unit_label' => fake()->optional()->randomElement(['pieza', 'bolsa', 'caja', 'paquete']),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ]);

        return [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit' => fake()->optional()->randomElement(['pieza', 'bolsa', 'caja', 'paquete']),
            'raw_text' => fake()->sentence(),
            'matched_text' => fake()->words(2, true),
            'confidence_score' => fake()->randomFloat(2, 0, 1),
            'notes' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
