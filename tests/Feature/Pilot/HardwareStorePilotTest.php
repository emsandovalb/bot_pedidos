<?php

namespace Tests\Feature\Pilot;

use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use App\Services\Messaging\Manager\MessagingManager;
use App\Services\Messaging\MessagingIngestionService;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HardwareStorePilotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-31 10:30:00'));
        config([
            'app.debug' => true,
            'messaging.notifications_sending_enabled' => false,
            'messaging.telegram_notifications_enabled' => false,
            'messaging.whatsapp_notifications_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_whatsapp_order_goes_through_real_pilot_pipeline_and_appears_in_do_now(): void
    {
        [$user] = $this->makePilotContext('Pilot WhatsApp Org');

        $message = 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE';
        $order = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'whatsapp',
            customerName: 'Maria Lopez',
            customerPhone: '50255571234',
            message: $message,
        );

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $order->customer_id,
        ]);
        $this->assertDatabaseHas('customer_identities', [
            'organization_id' => $user->organization_id,
            'provider' => 'whatsapp',
            'customer_id' => $order->customer_id,
        ]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertGreaterThanOrEqual(1, $order->orderItems->count());
        $this->assertSame(today()->toDateString(), $order->fulfillmentPlan?->requested_date?->toDateString());
        $this->assertSame('14:00:00', $order->fulfillmentPlan?->commitment_time);
        $this->assertSame('delivery', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('sinpe', $order->fulfillmentPlan?->payment_method);
        $this->assertContains($order->fulfillmentPlan?->priority_level, ['high', 'urgent']);

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $user->organization_id,
            'provider' => 'whatsapp',
            'raw_text' => $message,
        ]);

        $this->assertDatabaseHas('customer_identities', [
            'organization_id' => $user->organization_id,
            'provider' => 'whatsapp',
            'customer_id' => $order->customer_id,
        ]);

        $this->assertOrderInQueueSection($user, $order, 'do_now');
    }

    public function test_telegram_order_goes_through_real_pilot_pipeline_and_appears_in_next(): void
    {
        [$user] = $this->makePilotContext('Pilot Telegram Org');

        $baseTime = Carbon::parse('2026-07-31 10:30:00');

        for ($i = 1; $i <= 5; $i++) {
            Carbon::setTestNow($baseTime->copy()->addSeconds($i));

            $this->generateBusinessCustomMessage(
                user: $user,
                provider: 'whatsapp',
                customerName: 'Preload Customer ' . $i,
                customerPhone: '50255571' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                message: 'Ocupo 5 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
            );
        }

        Carbon::setTestNow($baseTime);

        $message = '3 pinturas para manana temprano, yo paso por ellas y pago en efectivo';
        $order = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'telegram',
            customerName: 'Maria Lopez',
            customerPhone: '90012345',
            message: $message,
        );

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $user->organization_id,
            'provider' => 'telegram',
            'raw_text' => $message,
        ]);
        $this->assertDatabaseHas('customer_identities', [
            'organization_id' => $user->organization_id,
            'provider' => 'telegram',
            'customer_id' => $order->customer_id,
        ]);
        $this->assertSame('telegram', $order->source_channel);
        $this->assertSame('pickup', $order->fulfillmentPlan?->delivery_method);
        $this->assertSame('cash', $order->fulfillmentPlan?->payment_method);
        $this->assertSame(today()->addDay()->toDateString(), $order->fulfillmentPlan?->requested_date?->toDateString());

        $this->assertOrderInQueueSection($user, $order, 'next');
    }

    public function test_duplicate_technical_webhook_only_creates_one_incoming_message_and_one_order(): void
    {
        [$user, $branch] = $this->makePilotContext('Pilot Duplicate Org');
        $service = app(MessagingIngestionService::class);

        $message = new IncomingMessageDTO(
            provider: 'telegram',
            external_message_id: 'dup-telegram-001',
            external_chat_id: '90020001',
            received_at: new DateTimeImmutable('2026-07-31 11:00:00'),
            external_user_id: '90020001',
            provider_username: 'customer.telegram',
            customer_name: 'Duplicate Customer',
            customer_phone: '90020001',
            message: '2 bolsas de jardin',
            raw_payload: [
                'source' => 'pilot',
                'message_id' => 'dup-telegram-001',
            ],
            attachments: [],
        );

        $first = $service->ingest($user->organization, $branch, $message);
        $second = $service->ingest($user->organization, $branch, $message);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['incoming_message']->id, $second['incoming_message']->id);
        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_business_duplicate_across_whatsapp_and_telegram_marks_second_order_possible_duplicate(): void
    {
        [$user] = $this->makePilotContext('Pilot Business Duplicate Org');

        $message = 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE';
        $firstOrder = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'whatsapp',
            customerName: 'Carlos Ramirez',
            customerPhone: '50255573333',
            message: $message,
        );

        $secondOrder = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'telegram',
            customerName: 'Carlos Ramirez',
            customerPhone: '50255573333',
            message: $message,
        );

        $this->assertSame($firstOrder->customer_id, $secondOrder->customer_id);
        $this->assertNotNull($secondOrder->possible_duplicate_of_order_id);
        $this->assertSame($firstOrder->id, $secondOrder->possible_duplicate_of_order_id);
        $this->assertSame(95.0, (float) $secondOrder->duplicate_score);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseHas('orders', [
            'organization_id' => $user->organization_id,
            'possible_duplicate_of_order_id' => $firstOrder->id,
        ]);
    }

    public function test_workflow_moves_confirmed_order_through_the_full_operator_flow(): void
    {
        [$user] = $this->makePilotContext('Pilot Workflow Org');

        $order = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'telegram',
            customerName: 'Workflow Customer',
            customerPhone: '90030001',
            message: '3 pinturas para manana temprano, yo paso por ellas y pago en efectivo',
        );

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_PREPARING, $order->status);

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_READY_FOR_DISPATCH, $order->status);

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertNotNull($order->dispatched_at);
    }

    public function test_notification_policy_generates_logs_without_calling_a_real_provider(): void
    {
        [$user] = $this->makePilotContext('Pilot Notification Org');

        $fakeProvider = null;
        $this->app->instance(MessagingManager::class, $this->fakeMessagingManager($fakeProvider));

        $order = $this->generateBusinessCustomMessage(
            user: $user,
            provider: 'telegram',
            customerName: 'Notification Customer',
            customerPhone: '90040001',
            message: '3 pinturas para manana temprano, yo paso por ellas y pago en efectivo',
        );

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertFalse($fakeProvider->sendCalled);
        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_ready_for_dispatch',
            'channel' => 'telegram',
            'status' => OrderNotificationLog::STATUS_SIMULATED,
        ]);
    }

    public function test_organization_isolation_hides_foreign_pilot_data(): void
    {
        [$firstUser] = $this->makePilotContext('Pilot Isolation Source Org');
        [$secondUser] = $this->makePilotContext('Pilot Isolation Target Org');

        $firstOrder = $this->generateBusinessCustomMessage(
            user: $firstUser,
            provider: 'whatsapp',
            customerName: 'Source Customer',
            customerPhone: '50255579991',
            message: 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
        );

        $this->actingAs($firstUser)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->assertJsonFragment(['customer_name' => $firstOrder->customer->name]);

        $this->actingAs($secondUser)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->assertJsonCount(0, 'inbox');
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function makePilotContext(string $organizationName): array
    {
        $organization = Organization::query()->create([
            'name' => $organizationName,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Pilot Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update([
            'owner_user_id' => $user->id,
        ]);

        $whatsappBranch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pilot WhatsApp Branch',
            'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
            'channel_identifier' => 'pilot-whatsapp-' . $organization->id,
            'status' => Branch::STATUS_ACTIVE,
        ]);

        Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pilot Telegram Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@pilot-telegram-' . $organization->id,
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertOk();

        return [$user->fresh(), Branch::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', Branch::CHANNEL_TYPE_TELEGRAM)
            ->firstOrFail()
            ->fresh()];
    }

    private function generateBusinessCustomMessage(
        User $user,
        string $provider,
        string $customerName,
        string $customerPhone,
        string $message,
    ): Order {
        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_custom_message',
                'provider' => $provider,
                'customer_mode' => 'new',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'message' => $message,
            ])
            ->assertOk()
            ->assertSee('Custom message injected through the production pipeline.');

        return Order::query()
            ->where('organization_id', $user->organization_id)
            ->where('source_channel', $provider)
            ->where('raw_message_text', $message)
            ->latest('id')
            ->with(['customer', 'orderItems.product', 'fulfillmentPlan', 'possibleDuplicateOf'])
            ->firstOrFail();
    }

    private function assertOrderInQueueSection(User $user, Order $order, string $sectionKey): void
    {
        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $sections = collect(data_get($payload, 'next_action_queue.sections', []));
        $section = $sections->firstWhere('key', $sectionKey);

        if ($sectionKey === 'do_now') {
            $cards = collect(data_get($section, 'cards', []));

            $this->assertTrue(
                $cards->contains(fn (array $card): bool => (int) data_get($card, 'id') === $order->id),
                sprintf('Order #%d was not found in the %s queue section.', $order->id, $sectionKey),
            );

            return;
        }

        $cards = collect(data_get($section, 'groups', []))
            ->flatMap(static fn (array $group): array => data_get($group, 'cards', []))
            ->values();

        $this->assertTrue(
            $cards->contains(fn (array $card): bool => (int) data_get($card, 'id') === $order->id),
            sprintf('Order #%d was not found in the %s queue section.', $order->id, $sectionKey),
        );
    }

    /**
     * @param  object|null  $provider
     */
    private function fakeMessagingManager(& $provider): MessagingManager
    {
        $provider = new class implements MessagingProvider
        {
            public bool $sendCalled = false;

            public function providerName(): string
            {
                return 'telegram';
            }

            public function connect(): ProviderHealth
            {
                return $this->health();
            }

            public function disconnect(): ProviderHealth
            {
                return $this->health();
            }

            public function health(): ProviderHealth
            {
                return new ProviderHealth(
                    provider: $this->providerName(),
                    status: 'healthy',
                    healthy: true,
                    connected: true,
                    last_ping: now(),
                    latency_ms: 1,
                    version: 'test',
                    webhook_status: 'verified',
                    token_status: 'configured',
                );
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(
                    provider: $this->providerName(),
                    receive_messages: true,
                    send_messages: true,
                    interactive_buttons: true,
                );
            }

            public function validateConfiguration(): ProviderValidationResult
            {
                return new ProviderValidationResult(true, [], [], now());
            }

            public function supports(string $capability): bool
            {
                return $this->capabilities()->toArray()[strtolower(trim($capability))] ?? false;
            }

            public function verifyWebhook(Request $request): WebhookVerificationResult
            {
                return new WebhookVerificationResult(
                    success: false,
                    status: 501,
                    provider: $this->providerName(),
                    message: 'Not implemented.',
                );
            }

            public function receive(Request $request)
            {
                return null;
            }

            public function send(OutgoingMessageDTO $message): MessagingSendResult
            {
                return $this->sendMessage($message);
            }

            public function refreshCredentials(): ProviderValidationResult
            {
                return $this->validateConfiguration();
            }

            public function receiveWebhook(Request $request): WebhookVerificationResult
            {
                return new WebhookVerificationResult(
                    success: false,
                    status: 501,
                    provider: $this->providerName(),
                    message: 'Not implemented.',
                );
            }

            public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
            {
                $this->sendCalled = true;

                return new MessagingSendResult(
                    success: false,
                    provider: $this->providerName(),
                    error: 'Send should not have been called.',
                );
            }

            public function markAsRead(string $externalMessageId)
            {
                return null;
            }

            public function healthCheck()
            {
                return $this->health()->toArray();
            }
        };

        return new class($provider) extends MessagingManager
        {
            public function __construct(private readonly MessagingProvider $provider)
            {
            }

            public function driver(?string $provider = null): MessagingProvider
            {
                return $this->provider;
            }
        };
    }
}
