<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\NotificationSetting;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\Manager\MessagingManager;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_order_can_move_to_preparing(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_PREPARING, $order->status);
        $this->assertNotNull($order->preparing_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_PREPARING,
            'changed_via' => 'admin_ui',
        ]);

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_preparing',
            'channel' => 'telegram',
            'status' => 'simulated',
        ]);
    }

    public function test_preparing_order_can_move_to_ready_for_dispatch(): void
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_READY_FOR_DISPATCH, $order->status);
        $this->assertNotNull($order->ready_for_dispatch_at);
    }

    public function test_ready_for_dispatch_order_can_move_to_dispatched(): void
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertNotNull($order->dispatched_at);
    }

    public function test_preparing_order_can_be_cancelled(): void
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_ready_for_dispatch_order_can_be_cancelled(): void
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_disabled_setting_creates_skipped_notification_log(): void
    {
        [$user, $order] = $this->makePendingReviewOrder();

        NotificationSetting::query()->updateOrCreate(
            [
                'organization_id' => $user->organization_id,
                'channel' => NotificationSetting::CHANNEL_TELEGRAM,
                'event' => NotificationSetting::EVENT_ORDER_CONFIRMED,
            ],
            [
                'is_enabled' => false,
                'requires_open_service_window' => false,
                'use_template_if_window_closed' => false,
                'template_name' => null,
                'message_body' => 'Pedido {order_id} confirmado',
            ]
        );

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_confirmed',
            'status' => 'skipped',
        ]);
    }

    public function test_whatsapp_closed_window_creates_skipped_notification_log(): void
    {
        [$user, $order] = $this->makePreparingOrder();
        $this->makeWhatsappContext($order, now()->subHour());

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_ready_for_dispatch',
            'channel' => 'whatsapp',
            'status' => 'skipped',
        ]);
    }

    public function test_whatsapp_open_window_creates_simulated_notification_log(): void
    {
        [$user, $order] = $this->makePreparingOrder();
        $this->makeWhatsappContext($order, now()->addHour());

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'event' => 'order_ready_for_dispatch',
            'channel' => 'whatsapp',
            'status' => 'simulated',
        ]);
    }

    public function test_notification_flow_does_not_call_real_provider_send(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $fakeProvider = new class implements MessagingProvider
        {
            public bool $sendCalled = false;

            public function verifyWebhook(Request $request): bool
            {
                return true;
            }

            public function receiveWebhook(Request $request)
            {
                return null;
            }

            public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
            {
                $this->sendCalled = true;

                throw new \RuntimeException('sendMessage should not be called.');
            }

            public function markAsRead(string $externalMessageId)
            {
                return null;
            }

            public function healthCheck()
            {
                return [
                    'provider' => $this->providerName(),
                    'status' => 'ok',
                ];
            }

            public function providerName(): string
            {
                return 'telegram';
            }
        };

        $fakeMessagingManager = new class($fakeProvider) extends MessagingManager
        {
            public function __construct(private readonly MessagingProvider $provider)
            {
            }

            public function driver(?string $provider = null): MessagingProvider
            {
                return $this->provider;
            }
        };

        app()->instance(MessagingManager::class, $fakeMessagingManager);

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertFalse($fakeProvider->sendCalled);
    }

    public function test_dispatched_order_cannot_be_cancelled(): void
    {
        [$user, $order] = $this->makeDispatchedOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertStatus(422);

        $order->refresh();

        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertNull($order->cancelled_at);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        [$user, $order] = $this->makePendingReviewOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertStatus(422);

        $order->refresh();

        $this->assertSame(Order::STATUS_PENDING_REVIEW, $order->status);
    }

    public function test_every_valid_transition_creates_order_status_history(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_PREPARING,
        ]);
    }

    public function test_timestamps_are_set_correctly(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->preparing_at);
        $this->assertNull($order->ready_for_dispatch_at);
        $this->assertNull($order->dispatched_at);

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->preparing_at);
        $this->assertNotNull($order->ready_for_dispatch_at);
        $this->assertNull($order->dispatched_at);

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->dispatched_at);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makePendingReviewOrder(): array
    {
        return $this->makeOrder();
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeConfirmedOrder(): array
    {
        [$user, $order] = $this->makeOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makePreparingOrder(): array
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeReadyForDispatchOrder(): array
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeDispatchedOrder(): array
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    private function makeWhatsappContext(Order $order, mixed $serviceWindowExpiresAt): void
    {
        $order->forceFill([
            'source_channel' => 'whatsapp',
        ])->save();

        CustomerIdentity::create([
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
     * @return array{0: User, 1: Order}
     */
    private function makeOrder(): array
    {
        $organization = Organization::create([
            'name' => 'Workflow Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Workflow Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@workflow',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Workflow Customer',
            'phone' => '555-0202',
            'external_id' => null,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Workflow Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
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
}
