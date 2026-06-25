<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\OrderDuplicateDetectionService;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
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
        $this->assertGreaterThan(0.9, (float) $incomingMessage->parser_confidence);
        $this->assertSame(Order::STATUS_PENDING_REVIEW, $incomingMessage->parse_status);
        $this->assertSame('Order parsed successfully.', $incomingMessage->status_reason);
        $this->assertNotNull($incomingMessage->processed_at);
    }

    public function test_order_ingestion_sets_duplicate_detection_metadata(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardÃ­n',
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
            rawMessageText: '2 bolsas de jardÃ­n',
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'raw_message_text' => '2 bolsas de jardÃ­n',
        ]);
        $this->assertNotNull($order->id);
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

    public function test_legacy_flow_is_not_affected_when_order_ingestion_is_disabled(): void
    {
        [$user, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9100, 4100, '1000 al 28 2pm', 'maria', 'Maria');

        Config::set('services.telegram.enabled', true);
        Config::set('services.telegram.bot_token', 'test-token');
        Config::set('services.telegram.default_branch_id', $branch->id);
        Config::set('services.order_ingestion.enabled', false);
        Http::fake($this->telegramResponses([$update]));

        $exitCode = Artisan::call('telegram:poll');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => '4100',
            'external_message_id' => '9100',
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

    /**
     * @return array{0: User, 1: Branch}
     */
    private function makeOrgWithTelegramBranchAndOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Telegram Order Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Telegram Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@loteriabot',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        collect([
            ['name' => '12:00 md', 'draw_time' => '12:00:00'],
            ['name' => '2:00 pm', 'draw_time' => '14:00:00'],
            ['name' => '5:00 pm', 'draw_time' => '17:00:00'],
            ['name' => '7:00 pm', 'draw_time' => '19:00:00'],
        ])->each(function (array $drawData) use ($organization): void {
            \App\Models\Draw::create([
                'organization_id' => $organization->id,
                'name' => $drawData['name'],
                'draw_time' => $drawData['draw_time'],
                'status' => \App\Models\Draw::STATUS_ACTIVE,
            ]);
        });

        return [$user->fresh(), $branch->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramUpdate(int $updateId, int $chatId, string $text, ?string $username = null, ?string $firstName = null): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId + 100,
                'date' => now()->timestamp,
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => array_filter([
                    'id' => $chatId,
                    'username' => $username,
                    'first_name' => $firstName,
                ], static fn ($value) => $value !== null),
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramResponses(array $updates): array
    {
        return [
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Loteria Bot',
                    'username' => 'loteriabot',
                ],
            ], 200),
            'https://api.telegram.org/bot*/getUpdates' => Http::response([
                'ok' => true,
                'result' => $updates,
            ], 200),
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 777,
                ],
            ], 200),
        ];
    }
}
