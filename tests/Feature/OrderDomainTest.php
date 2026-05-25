<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DailyOrderClosure;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_domain_migrations_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('product_aliases'));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertTrue(Schema::hasTable('order_status_histories'));
        $this->assertTrue(Schema::hasTable('manual_reviews'));
        $this->assertTrue(Schema::hasTable('daily_order_closures'));

        $this->assertTrue(Schema::hasColumn('incoming_messages', 'order_id'));
        $this->assertTrue(Schema::hasColumn('incoming_messages', 'parser_result_json'));
        $this->assertTrue(Schema::hasColumn('incoming_messages', 'parser_confidence'));
        $this->assertTrue(Schema::hasColumn('incoming_messages', 'parse_status'));
        $this->assertTrue(Schema::hasColumn('incoming_messages', 'status_reason'));
        $this->assertTrue(Schema::hasColumn('incoming_messages', 'processed_at'));
    }

    public function test_product_belongs_to_organization(): void
    {
        $product = Product::factory()->create();

        $this->assertNotNull($product->organization);
        $this->assertSame($product->organization_id, $product->organization->id);
    }

    public function test_product_alias_belongs_to_product(): void
    {
        $alias = ProductAlias::factory()->create();

        $this->assertNotNull($alias->product);
        $this->assertSame($alias->product_id, $alias->product->id);
    }

    public function test_order_belongs_to_customer_branch_and_organization(): void
    {
        $order = Order::factory()->create();

        $this->assertNotNull($order->organization);
        $this->assertNotNull($order->branch);
        $this->assertNotNull($order->customer);

        $this->assertSame($order->organization_id, $order->organization->id);
        $this->assertSame($order->branch_id, $order->branch->id);
        $this->assertSame($order->customer_id, $order->customer->id);
    }

    public function test_order_has_items(): void
    {
        $order = Order::factory()->create();
        $product = Product::query()->create([
            'organization_id' => $order->organization_id,
            'branch_id' => $order->branch_id,
            'name' => 'Bolsas de jardin',
            'normalized_name' => 'bolsas de jardin',
            'sku' => 'SKU-1000',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'bolsa',
            'raw_text' => '2 bolsas de jardin',
            'matched_text' => 'bolsas de jardin',
            'confidence_score' => 0.95,
            'notes' => null,
            'sort_order' => 0,
        ]);

        $this->assertCount(1, $order->fresh()->orderItems);
    }

    public function test_order_status_constants_and_helpers_work(): void
    {
        $pendingReview = Order::factory()->create([
            'status' => Order::STATUS_PENDING_REVIEW,
        ]);

        $confirmed = Order::factory()->create([
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $dispatched = Order::factory()->create([
            'status' => Order::STATUS_DISPATCHED,
        ]);

        $this->assertSame('pending_review', Order::STATUS_PENDING_REVIEW);
        $this->assertSame('confirmed', Order::STATUS_CONFIRMED);
        $this->assertSame('preparing', Order::STATUS_PREPARING);
        $this->assertSame('ready_for_dispatch', Order::STATUS_READY_FOR_DISPATCH);
        $this->assertSame('dispatched', Order::STATUS_DISPATCHED);
        $this->assertSame('cancelled', Order::STATUS_CANCELLED);
        $this->assertSame('rejected', Order::STATUS_REJECTED);

        $this->assertTrue($pendingReview->isPendingReview());
        $this->assertTrue($pendingReview->canBeReviewed());
        $this->assertFalse($pendingReview->canBeDispatched());

        $this->assertTrue($confirmed->isConfirmed());
        $this->assertTrue($confirmed->canBeDispatched());

        $this->assertTrue($dispatched->isDispatched());
    }

    public function test_status_history_and_manual_review_factories_create_records(): void
    {
        $history = OrderStatusHistory::factory()->create();
        $review = ManualReview::factory()->create();

        $this->assertDatabaseHas('order_status_histories', [
            'id' => $history->id,
            'to_status' => Order::STATUS_PENDING_REVIEW,
        ]);

        $this->assertDatabaseHas('manual_reviews', [
            'id' => $review->id,
            'decision' => 'approved',
        ]);
    }

    public function test_daily_closure_is_unique_by_branch_and_date(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizacion Prueba',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sucursal Prueba',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => 'branch-test-1',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        DailyOrderClosure::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'closure_date' => '2026-05-22',
            'closed_by' => null,
            'pending_review_count' => 1,
            'confirmed_count' => 2,
            'preparing_count' => 3,
            'ready_for_dispatch_count' => 4,
            'dispatched_count' => 5,
            'cancelled_count' => 0,
            'rejected_count' => 0,
            'total_orders' => 15,
            'total_items' => 20,
            'total_order_value' => 123.45,
            'notes' => null,
            'export_path' => null,
            'exported_at' => null,
            'closed_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DailyOrderClosure::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'closure_date' => '2026-05-22',
            'closed_by' => null,
            'pending_review_count' => 0,
            'confirmed_count' => 0,
            'preparing_count' => 0,
            'ready_for_dispatch_count' => 0,
            'dispatched_count' => 0,
            'cancelled_count' => 0,
            'rejected_count' => 0,
            'total_orders' => 0,
            'total_items' => 0,
            'total_order_value' => null,
            'notes' => null,
            'export_path' => null,
            'exported_at' => null,
            'closed_at' => now(),
        ]);
    }
}
