<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_endpoint_returns_full_order_items(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->getJson(route('operations.orders.snapshot', $fixture['selectedOrder']))
            ->assertOk()
            ->assertJsonPath('id', $fixture['selectedOrder']->id)
            ->assertJsonPath('status', Order::STATUS_PREPARING)
            ->assertJsonPath('items.0.product_name', 'Telegram especial')
            ->assertJsonPath('items.1.name', 'Saco extra')
            ->assertJsonPath('items.1.notes', 'Entrega en puerta')
            ->assertJsonPath('allowed_actions.0.key', 'ready')
            ->assertJsonPath('possible_duplicate', false);
    }

    public function test_snapshot_endpoint_includes_customer_context(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->getJson(route('operations.orders.snapshot', $fixture['selectedOrder']))
            ->assertOk()
            ->assertJsonPath('customer_context.total_orders', 2)
            ->assertJsonPath('customer_context.favorite_channel.name', 'Telegram')
            ->assertJsonPath('customer_context.open_notifications', 1)
            ->assertJsonPath('open_notifications', 1)
            ->assertJsonStructure([
                'customer_context' => [
                    'favorite_products',
                    'last_order',
                    'recent_activity',
                ],
                'recent_activity',
                'received_at',
            ]);
    }

    public function test_snapshot_endpoint_enforces_organization_isolation(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->getJson(route('operations.orders.snapshot', $fixture['foreignOrder']))
            ->assertNotFound();
    }

    public function test_operations_page_references_the_snapshot_endpoint(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $fixture['selectedOrder']->id]))
            ->assertOk()
            ->assertSee('snapshotUrlBase', false)
            ->assertSee('Cargando detalle del pedido...')
            ->assertSee('drawerLoading', false)
            ->assertSee('x-show="!drawerLoading && activeOrder.items.length === 0"', false);
    }

    /**
     * @return array{0: User, 1: array<string, mixed>}
     */
    private function makeOperationsFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Operations Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Operations Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@operations',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Operations Owner',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $telegramProduct = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Telegram especial',
                'sku' => 'TEL-02',
                'unit_label' => 'unidad',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Juan Telegram',
                'phone' => '+50255550002',
                'external_id' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:05:00'));
            $previousOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'telegram',
                'raw_message_text' => 'telegram previous order',
                'parser_confidence' => 0.93,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:10:00'));
            $selectedOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PREPARING,
                'source_channel' => 'telegram',
                'raw_message_text' => 'telegram especial',
                'parser_confidence' => 0.91,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            OrderItem::create([
                'order_id' => $selectedOrder->id,
                'product_id' => null,
                'quantity' => 1,
                'unit' => 'pieza',
                'raw_text' => 'Saco extra',
                'matched_text' => null,
                'confidence_score' => null,
                'notes' => 'Entrega en puerta',
                'sort_order' => 1,
            ]);

            $incomingMessage = IncomingMessage::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'provider' => 'telegram',
                'channel_type' => 'telegram',
                'from_identifier' => '@customer',
                'to_identifier' => '@operations',
                'raw_text' => 'telegram especial',
                'payload_json' => ['text' => 'telegram especial'],
                'external_message_id' => 'msg-telegram-snapshot',
                'status' => IncomingMessage::STATUS_RECEIVED,
                'received_at' => Carbon::parse('2026-06-30 09:09:00'),
            ]);

            $selectedOrder->forceFill([
                'incoming_message_id' => $incomingMessage->id,
            ])->saveQuietly();

            OrderNotificationLog::create([
                'organization_id' => $organization->id,
                'order_id' => $selectedOrder->id,
                'customer_id' => $customer->id,
                'channel' => 'telegram',
                'event' => 'order_ready_for_dispatch',
                'status' => OrderNotificationLog::STATUS_QUEUED,
                'should_send' => true,
                'requires_template' => false,
                'message_body' => 'Queued notification for snapshot test.',
                'reason' => 'Testing open notification count.',
                'provider' => 'telegram',
                'provider_message_id' => null,
                'sent_at' => null,
                'error_message' => null,
                'metadata_json' => [],
                'evaluated_at' => now(),
            ]);

            $foreignOrganization = Organization::create([
                'name' => 'Foreign Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $foreignBranch = Branch::create([
                'organization_id' => $foreignOrganization->id,
                'name' => 'Foreign Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => '@foreign',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $foreignCustomer = Customer::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'name' => 'Foreign Customer',
                'phone' => '+50255559999',
                'external_id' => null,
            ]);

            Order::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'customer_id' => $foreignCustomer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'whatsapp',
                'external_message_id' => 'foreign-msg-1',
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => 0.77,
                'raw_message_text' => 'foreign order',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'foreign-fingerprint',
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

            return [
                $user->fresh(),
                [
                    'branch' => $branch,
                    'customer' => $customer,
                    'telegramProduct' => $telegramProduct,
                    'previousOrder' => $previousOrder->fresh(['customer']),
                    'selectedOrder' => $selectedOrder->fresh(['customer']),
                    'foreignOrder' => Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
                ],
            ];
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createOrder(Customer $customer, Branch $branch, array $attributes, Product $product): Order
    {
        $order = Order::create([
            'organization_id' => $customer->organization_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'possible_duplicate_of_order_id' => $attributes['possible_duplicate_of_order_id'] ?? null,
            'source_channel' => $attributes['source_channel'],
            'external_message_id' => fake()->uuid(),
            'status' => $attributes['status'],
            'parser_confidence' => $attributes['parser_confidence'],
            'raw_message_text' => $attributes['raw_message_text'],
            'parsed_payload_json' => ['items' => []],
            'duplicate_score' => ($attributes['possible_duplicate_of_order_id'] ?? null) !== null ? 95 : null,
            'duplicate_reason' => ($attributes['possible_duplicate_of_order_id'] ?? null) !== null ? 'Possible duplicate order.' : null,
            'duplicate_checked_at' => now(),
            'order_fingerprint' => fake()->uuid(),
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => ! empty($attributes['reviewed_at']) ? now() : null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => $attributes['status'] === Order::STATUS_PREPARING ? now() : null,
            'ready_for_dispatch_at' => $attributes['status'] === Order::STATUS_READY_FOR_DISPATCH ? now() : null,
            'dispatched_at' => $attributes['status'] === Order::STATUS_DISPATCHED ? now() : null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'pack',
            'raw_text' => $attributes['raw_message_text'],
            'matched_text' => $attributes['raw_message_text'],
            'confidence_score' => $attributes['parser_confidence'],
            'notes' => null,
            'sort_order' => 0,
        ]);

        return $order->fresh(['customer', 'orderItems.product', 'possibleDuplicateOf']);
    }
}
