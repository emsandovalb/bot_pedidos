<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Services\CustomerInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_new_customer_is_classified_as_new(): void
    {
        [$organization, $branch, $customer] = $this->makeCustomerContext();

        $this->makeOrder($organization, $branch, $customer, Order::STATUS_CONFIRMED, 'whatsapp', '2026-06-24 08:00:00');

        $insights = app(CustomerInsightsService::class)->calculate($customer);

        $this->assertSame('NEW', $insights->segment);
        $this->assertSame(1, $insights->total_orders);
        $this->assertSame(0, $insights->completed_orders);
        $this->assertSame(0, $insights->cancelled_orders);
        $this->assertNull($insights->average_days);
    }

    public function test_customer_with_three_orders_is_classified_as_frequent(): void
    {
        [$organization, $branch, $customer] = $this->makeCustomerContext();

        $this->makeOrder($organization, $branch, $customer, Order::STATUS_CONFIRMED, 'whatsapp', '2026-06-24 08:00:00');
        $this->makeOrder($organization, $branch, $customer, Order::STATUS_CONFIRMED, 'telegram', '2026-06-25 08:00:00');
        $this->makeOrder($organization, $branch, $customer, Order::STATUS_DISPATCHED, 'whatsapp', '2026-06-26 08:00:00');

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('FREQUENT', $insights->segment);
        $this->assertSame(3, $insights->total_orders);
    }

    public function test_customer_with_twenty_orders_is_classified_as_vip(): void
    {
        [$organization, $branch, $customer] = $this->makeCustomerContext();

        for ($index = 0; $index < 20; $index++) {
            $this->makeOrder(
                $organization,
                $branch,
                $customer,
                $index % 4 === 0 ? Order::STATUS_DISPATCHED : Order::STATUS_CONFIRMED,
                $index % 2 === 0 ? 'whatsapp' : 'telegram',
                sprintf('2026-06-%02d 10:00:00', 1 + $index),
            );
        }

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('VIP', $insights->segment);
        $this->assertSame(20, $insights->total_orders);
        $this->assertSame(5, $insights->completed_orders);
    }

    public function test_customer_with_only_old_orders_is_inactive(): void
    {
        [$organization, $branch, $customer] = $this->makeCustomerContext();

        $this->makeOrder($organization, $branch, $customer, Order::STATUS_DISPATCHED, 'whatsapp', '2026-01-01 10:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-24 08:00:00'));

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('2026-01-01 10:00:00', $insights->last_order_date?->toDateTimeString());
        $this->assertSame('INACTIVE', $insights->segment);
    }

    public function test_favorite_products_are_calculated_from_order_items(): void
    {
        [$organization, $branch, $customer, $productA, $productB] = $this->makeCustomerContext(withProducts: true);

        $orderOne = $this->makeOrder($organization, $branch, $customer, Order::STATUS_CONFIRMED, 'whatsapp', '2026-06-24 08:00:00');
        $orderTwo = $this->makeOrder($organization, $branch, $customer, Order::STATUS_CONFIRMED, 'telegram', '2026-06-25 09:00:00');

        OrderItem::create([
            'order_id' => $orderOne->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit' => 'unidad',
            'raw_text' => '1 bolsa de jardin',
            'matched_text' => 'bolsa de jardin',
            'confidence_score' => 0.95,
            'notes' => null,
            'sort_order' => 0,
        ]);

        OrderItem::create([
            'order_id' => $orderTwo->id,
            'product_id' => $productA->id,
            'quantity' => 2,
            'unit' => 'unidad',
            'raw_text' => '2 bolsas de jardin',
            'matched_text' => 'bolsa de jardin',
            'confidence_score' => 0.96,
            'notes' => null,
            'sort_order' => 0,
        ]);

        OrderItem::create([
            'order_id' => $orderTwo->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'unit' => 'unidad',
            'raw_text' => '1 caja de vasos',
            'matched_text' => 'caja de vasos',
            'confidence_score' => 0.94,
            'notes' => null,
            'sort_order' => 1,
        ]);

        OrderItem::create([
            'order_id' => $orderOne->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'unit' => 'unidad',
            'raw_text' => '1 caja de vasos',
            'matched_text' => 'caja de vasos',
            'confidence_score' => 0.94,
            'notes' => null,
            'sort_order' => 1,
        ]);

        OrderItem::create([
            'order_id' => $orderTwo->id,
            'product_id' => null,
            'quantity' => 1,
            'unit' => 'unidad',
            'raw_text' => '1 bolsa de arena',
            'matched_text' => null,
            'confidence_score' => null,
            'notes' => null,
            'sort_order' => 2,
        ]);

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('Product A', $insights->favorite_products[0]['product']);
        $this->assertSame(2, $insights->favorite_products[0]['times_ordered']);
        $this->assertSame('Product B', $insights->favorite_products[1]['product']);
        $this->assertSame('1 bolsa de arena', $insights->favorite_products[2]['product']);
    }

    public function test_favorite_channel_is_calculated_from_orders(): void
    {
        [, , $customer] = $this->makeCustomerContext();
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-24 08:00:00');
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-24 09:00:00');
        $this->makeOrderOnDate($customer, 'telegram', '2026-06-24 10:00:00');

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('WhatsApp', $insights->favorite_channel['name']);
        $this->assertSame(66.67, $insights->favorite_channel['percentage']);
    }

    public function test_favorite_hour_is_calculated_from_orders(): void
    {
        [, , $customer] = $this->makeCustomerContext();
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-24 08:10:00');
        $this->makeOrderOnDate($customer, 'telegram', '2026-06-24 08:55:00');
        $this->makeOrderOnDate($customer, 'telegram', '2026-06-24 18:00:00');

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame('08:00', $insights->favorite_hour);
    }

    public function test_average_days_between_orders_is_calculated(): void
    {
        [, , $customer] = $this->makeCustomerContext();
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-01 08:00:00');
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-11 08:00:00');
        $this->makeOrderOnDate($customer, 'whatsapp', '2026-06-21 08:00:00');

        $insights = app(CustomerInsightsService::class)->calculate($customer->fresh());

        $this->assertSame(10.0, $insights->average_days);
    }

    public function test_service_handles_customers_without_orders(): void
    {
        [, , $customer] = $this->makeCustomerContext();

        $insights = app(CustomerInsightsService::class)->calculate($customer);

        $this->assertSame(0, $insights->total_orders);
        $this->assertSame('INACTIVE', $insights->segment);
        $this->assertSame('Unknown', $insights->favorite_channel['name']);
        $this->assertSame(0.0, $insights->favorite_channel['percentage']);
        $this->assertNull($insights->favorite_hour);
        $this->assertNull($insights->average_days);
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: Customer, 3?: Product, 4?: Product}
     */
    private function makeCustomerContext(bool $withProducts = false): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 08:00:00'));

        $organization = Organization::query()->create([
            'name' => 'Insights Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Insights Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@insights',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Insights Customer',
            'phone' => '555-0101',
            'external_id' => null,
        ]);

        if (! $withProducts) {
            return [$organization, $branch, $customer];
        }

        $productA = Product::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Product A',
            'sku' => 'SKU-A',
            'unit_label' => 'unidad',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $productB = Product::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Product B',
            'sku' => 'SKU-B',
            'unit_label' => 'unidad',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$organization, $branch, $customer, $productA, $productB];
    }

    private function makeOrder(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $status,
        string $sourceChannel,
        string $createdAt,
    ): Order {
        Carbon::setTestNow(Carbon::parse($createdAt));

        $order = Order::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => $sourceChannel,
            'external_message_id' => null,
            'status' => $status,
            'parser_confidence' => 0.98,
            'raw_message_text' => 'Test order',
            'parsed_payload_json' => ['items' => []],
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => null,
            'ready_for_dispatch_at' => null,
            'dispatched_at' => $status === Order::STATUS_DISPATCHED ? Carbon::parse($createdAt) : null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'created_at' => Carbon::parse($createdAt),
                'updated_at' => Carbon::parse($createdAt),
            ]);

        return $order;
    }

    private function makeOrderOnDate(Customer $customer, string $sourceChannel, string $createdAt): Order
    {
        Carbon::setTestNow(Carbon::parse($createdAt));

        $order = Order::query()->create([
            'organization_id' => $customer->organization_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => $sourceChannel,
            'external_message_id' => null,
            'status' => Order::STATUS_CONFIRMED,
            'parser_confidence' => 0.98,
            'raw_message_text' => 'Test order',
            'parsed_payload_json' => ['items' => []],
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => null,
            'ready_for_dispatch_at' => null,
            'dispatched_at' => null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'created_at' => Carbon::parse($createdAt),
                'updated_at' => Carbon::parse($createdAt),
            ]);

        return $order;
    }
}
