<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsLiveQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_the_feed_endpoint(): void
    {
        $this->get(route('operations.feed'))
            ->assertRedirect(route('login'));
    }

    public function test_feed_returns_counts_and_inbox_json_for_the_visible_organization(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $response = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->assertJsonStructure([
                'latest_order_id',
                'server_time',
                'counts' => [
                    'pending_review',
                    'confirmed',
                    'preparing',
                    'ready_for_dispatch',
                    'dispatched',
                ],
                'inbox' => [
                    [
                        'id',
                        'status',
                        'status_label',
                        'status_tone',
                        'channel',
                        'channel_key',
                        'customer_name',
                        'customer_phone',
                        'branch_name',
                        'elapsed_label',
                        'created_at_label',
                        'preview',
                        'items_count',
                        'recognized_items_count',
                        'unread',
                        'duplicate',
                        'vip',
                        'parser_confidence',
                        'update_url',
                        'show_url',
                    ],
                ],
            ])
            ->assertJsonPath('counts.pending_review', 1)
            ->assertJsonPath('counts.confirmed', 1)
            ->assertJsonPath('counts.preparing', 1)
            ->assertJsonPath('counts.ready_for_dispatch', 1)
            ->assertJsonPath('counts.dispatched', 1)
            ->assertJsonPath('latest_order_id', $fixture['latestOrder']->id);

        $inboxIds = array_map(
            static fn (array $order): int => (int) $order['id'],
            $response->json('inbox') ?? [],
        );

        $this->assertContains($fixture['latestOrder']->id, $inboxIds);
    }

    public function test_feed_respects_organization_isolation(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $response = $this->actingAs($user)->getJson(route('operations.feed'));

        $response
            ->assertOk()
            ->assertJsonMissing([
                'customer_name' => $fixture['foreignOrder']->customer->name,
            ])
            ->assertJsonMissing([
                'preview' => $fixture['foreignOrder']->raw_message_text,
            ]);
    }

    public function test_new_orders_advance_latest_order_id_and_inbox_ordering(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $initial = $this->actingAs($user)->getJson(route('operations.feed'))->json();

        Carbon::setTestNow(Carbon::parse('2026-06-30 11:30:00'));
        $newOrder = $this->createOrder(
            customer: $fixture['selectedCustomer'],
            branch: $fixture['branch'],
            attributes: [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'telegram',
                'raw_message_text' => 'telegram newest order',
                'parser_confidence' => 0.91,
                'possible_duplicate_of_order_id' => null,
            ],
            product: $fixture['telegramProduct'],
        );
        Carbon::setTestNow();

        $updated = $this->actingAs($user)->getJson(route('operations.feed'))->json();

        $this->assertSame($newOrder->id, $updated['latest_order_id']);
        $this->assertSame($newOrder->id, $updated['inbox'][0]['id']);
        $this->assertGreaterThan($initial['latest_order_id'], $updated['latest_order_id']);
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
                'name' => 'Telegram special',
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

            $pendingReviewOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'telegram',
                'raw_message_text' => 'pending review order',
                'parser_confidence' => 0.83,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:05:00'));
            $confirmedOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_CONFIRMED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'confirmed order',
                'parser_confidence' => 0.84,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:10:00'));
            $preparingOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PREPARING,
                'source_channel' => 'telegram',
                'raw_message_text' => 'preparing order',
                'parser_confidence' => 0.85,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:15:00'));
            $readyOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_READY_FOR_DISPATCH,
                'source_channel' => 'telegram',
                'raw_message_text' => 'ready order',
                'parser_confidence' => 0.86,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:20:00'));
            $dispatchedOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_DISPATCHED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'dispatched order',
                'parser_confidence' => 0.87,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

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
                    'selectedCustomer' => $customer,
                    'telegramProduct' => $telegramProduct,
                    'latestOrder' => $dispatchedOrder->fresh(['customer']),
                    'foreignOrder' => Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
                    'pendingReviewOrder' => $pendingReviewOrder,
                    'confirmedOrder' => $confirmedOrder,
                    'preparingOrder' => $preparingOrder,
                    'readyOrder' => $readyOrder,
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
