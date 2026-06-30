<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\NotificationSetting;
use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use App\Services\Messaging\Manager\MessagingManager;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderNotificationSendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_disabled_keeps_simulated_status_and_does_not_call_provider(): void
    {
        [$user, $order] = $this->makeOrder();

        $fakeProvider = null;
        $fakeMessagingManager = $this->makeFakeMessagingManager($fakeProvider);

        app()->instance(MessagingManager::class, $fakeMessagingManager);

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertFalse($fakeProvider->sendCalled);

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_confirmed',
            'channel' => 'telegram',
            'status' => OrderNotificationLog::STATUS_SIMULATED,
        ]);
    }

    public function test_telegram_enabled_sends_through_telegram_provider(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('services.telegram.bot_token', 'fake-token');

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 987,
                    'chat' => [
                        'id' => 'tg-chat-123',
                    ],
                ],
            ], 200),
        ]);

        [$user, $order] = $this->makeOrder();
        $this->makeTelegramIdentity($order, 'tg-chat-123');

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $log = OrderNotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', 'order_confirmed')
            ->latest('evaluated_at')
            ->firstOrFail();

        $this->assertSame(OrderNotificationLog::STATUS_SENT, $log->status);
        $this->assertSame('987', $log->provider_message_id);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);

        Http::assertSentCount(1);
    }

    public function test_missing_telegram_identity_creates_failed_log(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('services.telegram.bot_token', 'fake-token');

        [$user, $order] = $this->makeOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $log = OrderNotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', 'order_confirmed')
            ->latest('evaluated_at')
            ->firstOrFail();

        $this->assertSame(OrderNotificationLog::STATUS_FAILED, $log->status);
        $this->assertSame('Missing Telegram identity', $log->reason);
        $this->assertSame('Missing Telegram identity', $log->error_message);
    }

    public function test_missing_telegram_chat_id_creates_failed_log(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('services.telegram.bot_token', 'fake-token');

        [$user, $order] = $this->makeOrder();
        $this->makeTelegramIdentity($order, null);

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $log = OrderNotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', 'order_confirmed')
            ->latest('evaluated_at')
            ->firstOrFail();

        $this->assertSame(OrderNotificationLog::STATUS_FAILED, $log->status);
        $this->assertSame('Missing Telegram chat id', $log->reason);
        $this->assertSame('Missing Telegram chat id', $log->error_message);
    }

    public function test_provider_failure_creates_failed_log(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('services.telegram.bot_token', 'fake-token');

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 200),
        ]);

        [$user, $order] = $this->makeOrder();
        $this->makeTelegramIdentity($order, 'tg-chat-123');

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $log = OrderNotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', 'order_confirmed')
            ->latest('evaluated_at')
            ->firstOrFail();

        $this->assertSame(OrderNotificationLog::STATUS_FAILED, $log->status);
        $this->assertSame('Forbidden: bot was blocked by the user', $log->reason);
        $this->assertSame('Forbidden: bot was blocked by the user', $log->error_message);
        $this->assertNull($log->provider_message_id);
    }

    public function test_order_transition_does_not_fail_when_provider_fails(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('services.telegram.bot_token', 'fake-token');

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 200),
        ]);

        [$user, $order] = $this->makeOrder();
        $this->makeTelegramIdentity($order, 'tg-chat-123');

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
    }

    public function test_whatsapp_remains_non_sending_placeholder(): void
    {
        config()->set('messaging.notifications_sending_enabled', true);
        config()->set('messaging.telegram_notifications_enabled', true);
        config()->set('messaging.whatsapp_notifications_enabled', true);

        $fakeProvider = null;
        $fakeMessagingManager = $this->makeFakeMessagingManager($fakeProvider);

        app()->instance(MessagingManager::class, $fakeMessagingManager);

        [$user, $order] = $this->makePreparingOrder();
        $order->forceFill(['source_channel' => 'whatsapp'])->save();
        $this->makeWhatsappIdentity($order, now()->addHour());

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $log = OrderNotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', 'order_ready_for_dispatch')
            ->latest('evaluated_at')
            ->firstOrFail();

        $this->assertSame(OrderNotificationLog::STATUS_SIMULATED, $log->status);
        $this->assertFalse($fakeProvider->sendCalled);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeOrder(): array
    {
        $organization = Organization::create([
            'name' => 'Notification Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Notification Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@notification-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Notification Customer',
            'phone' => '+50255550003',
            'external_id' => null,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Notification Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardin',
        );

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makePreparingOrder(): array
    {
        [$user, $order] = $this->makeOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    private function makeTelegramIdentity(Order $order, ?string $chatId): CustomerIdentity
    {
        return CustomerIdentity::create([
            'organization_id' => $order->organization_id,
            'customer_id' => $order->customer_id,
            'provider' => 'telegram',
            'external_user_id' => 'tg-user-' . $order->id,
            'external_chat_id' => $chatId,
            'provider_username' => 'customer.telegram',
            'phone' => $order->customer->phone,
            'normalized_phone' => $order->customer->phone,
            'email' => null,
            'display_name' => $order->customer->name,
            'confidence_score' => 100,
            'is_primary' => true,
            'metadata_json' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_customer_message_at' => now(),
            'service_window_expires_at' => now()->addDay(),
        ]);
    }

    private function makeWhatsappIdentity(Order $order, mixed $serviceWindowExpiresAt): CustomerIdentity
    {
        return CustomerIdentity::create([
            'organization_id' => $order->organization_id,
            'customer_id' => $order->customer_id,
            'provider' => 'whatsapp',
            'external_user_id' => 'wa-user-' . $order->id,
            'external_chat_id' => 'wa-chat-' . $order->id,
            'provider_username' => 'customer.whatsapp',
            'phone' => $order->customer->phone,
            'normalized_phone' => $order->customer->phone,
            'email' => null,
            'display_name' => $order->customer->name,
            'confidence_score' => 100,
            'is_primary' => true,
            'metadata_json' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_customer_message_at' => now(),
            'service_window_expires_at' => $serviceWindowExpiresAt,
        ]);
    }

    /**
     * @param  object|null  $provider
     */
    private function makeFakeMessagingManager(& $provider): MessagingManager
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
