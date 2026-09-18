<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 — precio en catálogo + total en el pedido.
 */
class OrderPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingested_order_prices_lines_and_totals_from_catalog(): void
    {
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: 3.50);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '4 bolsas de jardin', sourceChannel: 'telegram',
        );

        $item = $order->orderItems->first();
        $this->assertSame('3.50', (string) $item->unit_price);
        $this->assertSame('14.00', (string) $item->line_total);
        $this->assertSame('14.00', (string) $order->items_total);
    }

    public function test_order_with_unpriced_product_has_null_total(): void
    {
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: null);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '4 bolsas de jardin', sourceChannel: 'telegram',
        );

        $this->assertNull($order->orderItems->first()->unit_price);
        $this->assertNull($order->fresh()->items_total);
    }

    public function test_editing_quantity_recalculates_the_total(): void
    {
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: 3.50);
        $user = $this->owner($org);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '4 bolsas de jardin', sourceChannel: 'telegram',
        );
        $itemId = $order->orderItems->first()->id;

        $this->actingAs($user)->patch(route('orders.update', $order), [
            'items_json' => json_encode([['id' => (string) $itemId, 'quantity' => 10, 'raw_text' => '10 bolsas de jardin']]),
        ])->assertRedirect();

        $this->assertSame('35.00', (string) $order->fresh()->items_total);
    }

    public function test_enforce_pricing_blocks_confirming_unpriced_catalog_item(): void
    {
        config(['pilot.enforce_pricing' => true]);
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: null);
        $user = $this->owner($org);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '4 bolsas de jardin', sourceChannel: 'telegram',
        );

        $this->actingAs($user)->post(route('orders.confirm', $order))->assertStatus(422);
        $this->assertSame(Order::STATUS_PENDING_REVIEW, $order->fresh()->status);
    }

    public function test_assigning_a_product_to_an_unmatched_item_resolves_and_prices_it(): void
    {
        [$org, $branch, $customer] = $this->context();
        // Sin catálogo: el ítem entra sin producto.
        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '5 bloques', sourceChannel: 'telegram',
        );
        $item = $order->orderItems->first();
        $this->assertNull($item->product_id);
        $this->assertSame(Order::STATUS_PENDING_REVIEW, $order->status);

        // Ahora existe el producto y el revisor lo asigna.
        $this->catalogProduct($org, 'Bloques de concreto', 'bloque', price: 0.65);
        $product = Product::where('organization_id', $org->id)->firstOrFail();
        $user = $this->owner($org);

        $this->actingAs($user)->patch(route('orders.update', $order), [
            'items_json' => json_encode([[
                'id' => (string) $item->id,
                'quantity' => 5,
                'raw_text' => '5 bloques',
                'product_id' => $product->id,
            ]]),
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('0.65', (string) $item->unit_price);
        $this->assertSame('3.25', (string) $order->fresh()->items_total);

        // Y el pedido ya se puede confirmar (la edición manual cuenta como revisión).
        $this->actingAs($user)->post(route('orders.confirm', $order))->assertRedirect();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_product_id_from_another_organization_is_rejected(): void
    {
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: 3.50);
        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '2 bolsas de jardin', sourceChannel: 'telegram',
        );

        $other = Organization::create(['name' => 'Otra', 'status' => Organization::STATUS_ACTIVE]);
        $foreignProduct = Product::create([
            'organization_id' => $other->id, 'branch_id' => null, 'name' => 'Ajeno',
            'sku' => 'X', 'unit_label' => 'u', 'price' => 9.99, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($this->owner($org))->patch(route('orders.update', $order), [
            'items' => [(string) $order->orderItems->first()->id => ['quantity' => 2, 'product_id' => $foreignProduct->id]],
        ])->assertSessionHasErrors('items.'.$order->orderItems->first()->id.'.product_id');
    }

    public function test_pricing_not_enforced_by_default(): void
    {
        [$org, $branch, $customer] = $this->context();
        $this->catalogProduct($org, 'Bolsas de jardin', 'bolsas de jardin', price: null);
        $user = $this->owner($org);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $org, branch: $branch, customer: $customer,
            rawMessageText: '4 bolsas de jardin', sourceChannel: 'telegram',
        );

        $this->actingAs($user)->post(route('orders.confirm', $order))->assertRedirect();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /** @return array{0: Organization, 1: Branch, 2: Customer} */
    private function context(): array
    {
        $org = Organization::create(['name' => 'Pricing Org', 'status' => Organization::STATUS_ACTIVE]);
        $branch = Branch::create([
            'organization_id' => $org->id, 'name' => 'Branch', 'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@pricing', 'status' => Branch::STATUS_ACTIVE,
        ]);
        $customer = Customer::create([
            'organization_id' => $org->id, 'branch_id' => $branch->id, 'name' => 'C', 'phone' => '5551', 'external_id' => null,
        ]);

        return [$org, $branch, $customer];
    }

    private function owner(Organization $org): User
    {
        $u = User::create([
            'organization_id' => $org->id, 'branch_id' => null, 'role' => User::ROLE_OWNER,
            'name' => 'Owner', 'email' => fake()->unique()->safeEmail(), 'email_verified_at' => now(), 'password' => 'password',
        ]);
        $org->update(['owner_user_id' => $u->id]);

        return $u;
    }

    private function catalogProduct(Organization $org, string $name, string $alias, ?float $price): void
    {
        $product = Product::create([
            'organization_id' => $org->id, 'branch_id' => null, 'name' => $name,
            'sku' => 'P-'.strtoupper(substr(md5($name), 0, 6)), 'unit_label' => 'unidad',
            'price' => $price, 'is_active' => true, 'sort_order' => 0,
        ]);
        ProductAlias::create([
            'organization_id' => $org->id, 'product_id' => $product->id, 'alias' => $alias,
            'match_weight' => 100, 'is_active' => true,
        ]);
    }
}
