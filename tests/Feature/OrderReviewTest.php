<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_orders_index(): void
    {
        [$user] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk();
    }

    public function test_orders_index_shows_pending_review_order(): void
    {
        [$user, $order] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee((string) $order->id)
            ->assertSee('Pendiente de revisión');
    }

    public function test_authenticated_user_can_view_order_detail(): void
    {
        [$user, $order] = $this->makeUserAndOrder(true);

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->raw_message_text)
            ->assertSee('Producto reconocido')
            ->assertSee('Bolsas de jardin')
            ->assertSee('Coincidencia')
            ->assertSee('Confianza');
    }

    public function test_order_detail_shows_unmatched_label_when_product_id_is_null(): void
    {
        [$user, $order] = $this->makeUserAndOrder(false, '2 bolsas de arena');

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Sin producto asociado');
    }

    public function test_orders_index_shows_classification_indicator(): void
    {
        [$user] = $this->makeUserAndOrder(false);

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Pendiente de clasificar');
    }

    public function test_order_review_queue_shows_matched_product_name_when_present(): void
    {
        [$user] = $this->makeUserAndOrder(true);

        $this->actingAs($user)
            ->get(route('order-reviews.index'))
            ->assertOk()
            ->assertSee('Clasificado')
            ->assertSee('Producto reconocido: Bolsas de jardin')
            ->assertSee('Confianza');
    }

    public function test_order_review_queue_shows_unmatched_label_when_product_id_is_null(): void
    {
        [$user] = $this->makeUserAndOrder(false, '2 bolsas de arena');

        $this->actingAs($user)
            ->get(route('order-reviews.index'))
            ->assertOk()
            ->assertSee('Pendiente de clasificar')
            ->assertSee('Sin producto asociado');
    }

    public function test_confirming_pending_review_order_sets_status_confirmed(): void
    {
        [$user, $order] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame($user->id, $order->confirmed_by);
        $this->assertNotNull($order->confirmed_at);
    }

    public function test_rejecting_pending_review_order_sets_status_rejected(): void
    {
        [$user, $order] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->post(route('orders.reject', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_REJECTED, $order->status);
        $this->assertNotNull($order->rejected_at);
    }

    public function test_cancelling_pending_review_order_sets_status_cancelled(): void
    {
        [$user, $order] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_status_changes_create_order_status_history(): void
    {
        [$user, $order] = $this->makeUserAndOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_PENDING_REVIEW,
            'to_status' => Order::STATUS_CONFIRMED,
            'changed_by_user_id' => $user->id,
            'changed_via' => 'admin_ui',
        ]);
    }

    public function test_updating_order_notes_and_items_creates_manual_review_row(): void
    {
        [$user, $order] = $this->makeUserAndOrder(true, '2 bolsas de jardin');
        $item = $order->orderItems->first();

        $this->actingAs($user)
            ->get(route('orders.edit', $order))
            ->assertOk()
            ->assertSee('Producto reconocido')
            ->assertSee('Bolsas de jardin')
            ->assertSee('Coincidencia')
            ->assertSee('Confianza');

        $payload = [
            'notes' => 'Adjusted during manual review.',
            'items' => [
                $item->id => [
                    'quantity' => 3,
                    'unit' => 'caja',
                    'raw_text' => '3 cajas de jardin',
                    'notes' => 'Updated quantity and unit.',
                ],
            ],
        ];

        $this->actingAs($user)
            ->patch(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order));

        $order->refresh()->load('orderItems', 'manualReviews');

        $this->assertSame('Adjusted during manual review.', $order->notes);
        $this->assertSame(3.0, (float) $order->orderItems->first()->quantity);
        $this->assertSame('caja', $order->orderItems->first()->unit);
        $this->assertCount(1, $order->manualReviews);
        $this->assertDatabaseHas('manual_reviews', [
            'order_id' => $order->id,
            'reviewed_by_user_id' => $user->id,
            'decision' => 'edited',
        ]);

        $review = ManualReview::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('Adjusted during manual review.', $review->after_json['notes']);
        $this->assertSame('Updated quantity and unit.', $review->after_json['items'][0]['notes']);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeUserAndOrder(bool $seedMatchingProduct = false, string $rawText = '2 bolsas de jardin'): array
    {
        $organization = Organization::create([
            'name' => 'Order Review Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Order Review Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@orders-review',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Review Customer',
            'phone' => '555-0101',
            'external_id' => null,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Admin User',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        if ($seedMatchingProduct) {
            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Bolsas de jardin',
                'sku' => 'JARDIN-01',
                'unit_label' => 'bolsa',
                'is_active' => true,
                'sort_order' => 0,
            ]);

            ProductAlias::create([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'alias' => 'bolsas de jardin',
                'match_weight' => 100,
                'is_active' => true,
            ]);
        }

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: $rawText,
        );

        return [$user, $order->fresh()->load('orderItems')];
    }
}
