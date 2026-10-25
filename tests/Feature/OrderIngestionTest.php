<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\Fulfillment\FulfillmentPlannerService;
use App\Services\OrderDuplicateDetectionService;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_ingestion_creates_pending_review_order(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardín',
        );

        $this->assertTrue($order->isPendingReview());
        $this->assertSame($organization->id, $order->organization_id);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('2 bolsas de jardín', $order->raw_message_text);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_order_ingestion_stores_extracted_notes_in_order_notes(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: 'Me aparta 3 kilos de carne y 2 paquetes de tortillas para mañana urgente',
        );

        $this->assertSame('para manana, urgente', $order->notes);
        $this->assertSame(['para manana', 'urgente'], $order->parsed_payload_json['notes']);
        $this->assertSame('para manana, urgente', $order->parsed_payload_json['notes_text']);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => 'para manana, urgente',
        ]);
    }

    public function test_order_ingestion_creates_order_items(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '5 bolsas de apretados y 1 caja de vasos',
        );

        $this->assertCount(2, $order->orderItems);
        $this->assertEquals(5, (float) $order->orderItems[0]->quantity);
        $this->assertSame('bolsa', $order->orderItems[0]->unit);
        $this->assertSame('5 bolsas de apretados', $order->orderItems[0]->raw_text);
        $this->assertSame('bolsas de apretados', $order->orderItems[0]->matched_text);
        $this->assertEquals(1, (float) $order->orderItems[1]->quantity);
        $this->assertSame('caja', $order->orderItems[1]->unit);
        $this->assertSame('1 caja de vasos', $order->orderItems[1]->raw_text);
        $this->assertSame('caja de vasos', $order->orderItems[1]->matched_text);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_order_ingestion_attaches_product_id_when_alias_matches(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

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

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardin',
        );

        $this->assertSame($product->id, $order->orderItems->first()->product_id);
        $this->assertSame('bolsas de jardin', $order->orderItems->first()->matched_text);
        $this->assertGreaterThan(0.9, (float) $order->orderItems->first()->confidence_score);
    }

    public function test_order_ingestion_leaves_product_id_null_when_no_match_exists(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de arena',
        );

        $this->assertNull($order->orderItems->first()->product_id);
        $this->assertSame('bolsas de arena', $order->orderItems->first()->matched_text);
        $this->assertSame('unmatched', $order->parsed_payload_json['status']);
        $this->assertTrue($order->parsed_payload_json['needs_review']);
        $this->assertLessThan(0.85, (float) $order->parser_confidence);
        $this->assertSame(0.15, (float) $order->orderItems->first()->confidence_score);
        $this->assertSame('2 bolsas de arena', $order->raw_message_text);
    }

    public function test_partially_similar_catalog_alias_is_not_resolved_as_a_product(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

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

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardinero',
        );

        $this->assertNotNull($order);
        $this->assertNull($order->orderItems->first()->product_id);
        $this->assertSame('unmatched', $order->parsed_payload_json['status']);
        $this->assertLessThan(0.85, (float) $order->parser_confidence);
    }

    public function test_order_ingestion_updates_incoming_message_tracking_fields(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();
        $incomingMessage = $this->makeIncomingMessage($organization, $branch, $customer, '2 bolsas de jardín');

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardín',
            externalMessageId: 'msg-123',
            incomingMessage: $incomingMessage,
        );

        $incomingMessage->refresh();

        $this->assertSame($order->id, $incomingMessage->order_id);
        $this->assertIsArray($incomingMessage->parser_result_json);
        $this->assertLessThanOrEqual(0.84, (float) $incomingMessage->parser_confidence);
        $this->assertSame('unmatched', $incomingMessage->parse_status);
        $this->assertSame('One or more parsed products could not be resolved in the active catalog.', $incomingMessage->status_reason);
        $this->assertNotNull($incomingMessage->processed_at);
    }

    public function test_order_ingestion_sets_duplicate_detection_metadata(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardín',
        );

        $this->assertNotNull($order->duplicate_checked_at);
        $this->assertNotEmpty($order->order_fingerprint);
    }

    public function test_order_ingestion_ignores_duplicate_detection_failures(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $duplicateDetector = Mockery::mock(OrderDuplicateDetectionService::class);
        $duplicateDetector->shouldReceive('detect')
            ->once()
            ->andThrow(new RuntimeException('Duplicate detector unavailable.'));

        $this->app->instance(OrderDuplicateDetectionService::class, $duplicateDetector);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardín',
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'raw_message_text' => '2 bolsas de jardín',
        ]);
        $this->assertNotNull($order->id);
    }

    public function test_parsing_failure_does_not_block_order_creation(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $planner = Mockery::mock(FulfillmentPlannerService::class)->makePartial();
        $planner->shouldReceive('parseIntentFromMessage')
            ->once()
            ->andThrow(new RuntimeException('Parser unavailable.'));

        $this->app->instance(FulfillmentPlannerService::class, $planner);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: 'Ocupo 20 bloques para manana temprano, yo paso por ellos y pago por SINPE',
        );

        $this->assertNotNull($order->id);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'raw_message_text' => 'Ocupo 20 bloques para manana temprano, yo paso por ellos y pago por SINPE',
        ]);
        $this->assertSame(config('fulfillment.defaults.delivery_method', 'unknown'), $order->fulfillmentPlan->delivery_method);
    }

    public function test_order_ingestion_enriches_fulfillment_plan(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: 'Ocupo 20 bloques para manana temprano, yo paso por ellos y pago por SINPE',
        );

        $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

        $this->assertNotNull($plan);
        $this->assertSame(today()->addDay()->toDateString(), $plan->requested_date?->toDateString());
        $this->assertSame('morning', $plan->requested_time_window);
        $this->assertSame('pickup', $plan->delivery_method);
        $this->assertSame('sinpe', $plan->payment_method);
        $this->assertSame('normal', $plan->priority_level);
        $this->assertSame(config('fulfillment.priority_scores.normal', 40), $plan->priority_score);
        $this->assertGreaterThan(0, $plan->planner_confidence);
        $this->assertArrayHasKey('fulfillment_intent', $plan->metadata_json);
    }

    public function test_order_status_history_is_created(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardín',
        );

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => Order::STATUS_PENDING_REVIEW,
            'changed_via' => 'system',
        ]);
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: Customer}
     */
    private function makeOrganizationBranchCustomer(): array
    {
        $organization = Organization::create([
            'name' => 'Order Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Order Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@orders',
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

    private function makeIncomingMessage(Organization $organization, Branch $branch, Customer $customer, string $rawText): IncomingMessage
    {
        return IncomingMessage::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => $customer->phone,
            'to_identifier' => $branch->channel_identifier,
            'raw_text' => $rawText,
            'payload_json' => ['source' => 'telegram'],
            'external_message_id' => null,
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);
    }
}
