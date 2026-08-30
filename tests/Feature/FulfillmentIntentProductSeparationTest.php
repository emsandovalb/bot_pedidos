<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Services\OrderIngestionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentIntentProductSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, config('app.timezone', 'UTC')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mixed_intent_phrases_do_not_become_order_items(): void
    {
        $order = $this->ingest('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE');

        $this->assertCount(1, $order->orderItems);
        $this->assertSame('bloques', data_get($order->parsed_payload_json, 'items.0.product_name'));
        $this->assertSame(20, (int) data_get($order->parsed_payload_json, 'items.0.quantity'));
        $this->assertSame('delivery', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('sinpe', $order->fulfillmentPlan?->payment_method);
        $this->assertSame('2026-08-10', $order->fulfillmentPlan?->requested_date?->toDateString());
        $this->assertSame('14:00:00', $order->fulfillmentPlan?->commitment_time);
    }

    public function test_today_request_keeps_only_real_products_and_intents(): void
    {
        $order = $this->ingest('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE');

        $this->assertCount(1, $order->orderItems);
        $this->assertSame('bloques', data_get($order->parsed_payload_json, 'items.0.product_name'));
        $this->assertSame('delivery', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('sinpe', $order->fulfillmentPlan?->payment_method);
        $this->assertSame('2026-08-10', $order->fulfillmentPlan?->requested_date?->toDateString());
        $this->assertSame('14:00:00', $order->fulfillmentPlan?->commitment_time);
        $this->assertSame('high', $order->fulfillmentPlan?->priority_level);
    }

    public function test_tomorrow_pickup_keeps_only_real_products_and_intents(): void
    {
        $order = $this->ingest('Necesito 3 pinturas para mañana, yo paso por ellas y pago en efectivo');

        $this->assertCount(1, $order->orderItems);
        $this->assertSame('pinturas', data_get($order->parsed_payload_json, 'items.0.product_name'));
        $this->assertSame('pickup', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('cash', $order->fulfillmentPlan?->payment_method);
        $this->assertSame('2026-08-11', $order->fulfillmentPlan?->requested_date?->toDateString());
        $this->assertSame('normal', $order->fulfillmentPlan?->priority_level);
    }

    public function test_urgent_transfer_keeps_only_real_products_and_intents(): void
    {
        $order = $this->ingest('Mandame 5 sacos urgente, pago por transferencia');

        $this->assertCount(1, $order->orderItems);
        $this->assertSame('sacos', data_get($order->parsed_payload_json, 'items.0.product_name'));
        $this->assertSame('unknown', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('transfer', $order->fulfillmentPlan?->payment_method);
        $this->assertNull($order->fulfillmentPlan?->requested_date);
        $this->assertSame('high', $order->fulfillmentPlan?->priority_level);
    }

    public function test_pickup_in_store_keeps_only_real_products_and_intents(): void
    {
        $order = $this->ingest('2 cajas de clavos, retiro en tienda');

        $this->assertCount(1, $order->orderItems);
        $this->assertSame('clavos', data_get($order->parsed_payload_json, 'items.0.product_name'));
        $this->assertSame('pickup', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('unknown', $order->fulfillmentPlan?->payment_method);
        $this->assertNull($order->fulfillmentPlan?->requested_date);
        $this->assertSame('normal', $order->fulfillmentPlan?->priority_level);
    }

    public function test_pure_intent_messages_do_not_fabricate_products(): void
    {
        $order = $this->ingest('pago por sinpe');

        $this->assertCount(0, $order->orderItems);
        $this->assertSame([], data_get($order->parsed_payload_json, 'items'));
        $this->assertSame('sinpe', $order->fulfillmentPlan?->payment_method);
        $this->assertNull($order->fulfillmentPlan?->requested_date);
        $this->assertNull($order->fulfillmentPlan?->commitment_date);
    }

    private function ingest(string $message): Order
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        return app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: $message,
        );
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: Customer}
     */
    private function makeOrganizationBranchCustomer(): array
    {
        $organization = Organization::create([
            'name' => 'Intent Separation Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Intent Separation Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@intent-separation',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Customer One',
            'phone' => '5550001',
            'external_id' => null,
        ]);

        return [$organization, $branch, $customer];
    }
}
