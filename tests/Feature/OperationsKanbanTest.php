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

class OperationsKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_renders_four_production_columns_and_the_drawer_entry_path(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $fixture['preparingOrder']->id]))
            ->assertOk()
            ->assertSeeText('Benditio Operations Center')
            ->assertSeeText('Bandeja inteligente')
            ->assertSeeText('Nuevos')
            ->assertSeeText('Preparando')
            ->assertSeeText('Listos')
            ->assertSeeText('Despachados')
            ->assertSeeText('Promedio')
            ->assertSeeText('Contexto del cliente')
            ->assertSeeText($fixture['preparingOrder']->customer->name);
    }

    public function test_board_exposes_a_nested_safe_select_hook_for_kanban_cards(): void
    {
        [$user] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('operations-select-order')
            ->assertDontSee('$root.select');
    }

    public function test_cards_map_into_the_expected_kanban_columns(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $cardsByColumn = [
            'new' => [],
            'preparing' => [],
            'ready' => [],
            'dispatched' => [],
        ];

        foreach ($payload['inbox'] ?? [] as $card) {
            $cardsByColumn[$this->columnKeyForStatus($card['status'] ?? '')][] = (int) $card['id'];
        }

        $this->assertContains($fixture['pendingReviewOrder']->id, $cardsByColumn['new']);
        $this->assertContains($fixture['confirmedOrder']->id, $cardsByColumn['new']);
        $this->assertContains($fixture['preparingOrder']->id, $cardsByColumn['preparing']);
        $this->assertContains($fixture['readyOrder']->id, $cardsByColumn['ready']);
        $this->assertContains($fixture['dispatchedOrder']->id, $cardsByColumn['dispatched']);
    }

    public function test_status_transitions_move_cards_and_update_counters(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $before = $this->actingAs($user)->getJson(route('operations.feed'))->json();

        $this->actingAs($user)
            ->postJson(route('orders.confirm', $fixture['pendingReviewOrder']))
            ->assertOk()
            ->assertJsonPath('order.status', Order::STATUS_CONFIRMED);

        $after = $this->actingAs($user)->getJson(route('operations.feed'))->json();

        $this->assertSame(1, $before['counts']['pending_review']);
        $this->assertSame(1, $before['counts']['confirmed']);
        $this->assertSame(0, $after['counts']['pending_review']);
        $this->assertSame(2, $after['counts']['confirmed']);
        $updatedCard = collect($after['inbox'] ?? [])->firstWhere('id', $fixture['pendingReviewOrder']->id);
        $this->assertSame(Order::STATUS_CONFIRMED, $updatedCard['status'] ?? null);
    }

    public function test_organization_isolation_hides_foreign_orders(): void
    {
        [$user, $fixture] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertDontSeeText($fixture['foreignOrder']->customer->name)
            ->assertDontSeeText($fixture['foreignOrder']->raw_message_text);
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

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:05:00'));
            $pendingReviewOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'telegram',
                'raw_message_text' => 'pending review order',
                'parser_confidence' => 0.83,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:10:00'));
            $confirmedOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_CONFIRMED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'confirmed order',
                'parser_confidence' => 0.84,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:15:00'));
            $preparingOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_PREPARING,
                'source_channel' => 'telegram',
                'raw_message_text' => 'preparing order',
                'parser_confidence' => 0.85,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:20:00'));
            $readyOrder = $this->createOrder($customer, $branch, [
                'status' => Order::STATUS_READY_FOR_DISPATCH,
                'source_channel' => 'telegram',
                'raw_message_text' => 'ready order',
                'parser_confidence' => 0.86,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:25:00'));
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
                    'customer' => $customer,
                    'telegramProduct' => $telegramProduct,
                    'pendingReviewOrder' => $pendingReviewOrder->fresh(['customer']),
                    'confirmedOrder' => $confirmedOrder->fresh(['customer']),
                    'preparingOrder' => $preparingOrder->fresh(['customer']),
                    'readyOrder' => $readyOrder->fresh(['customer']),
                    'dispatchedOrder' => $dispatchedOrder->fresh(['customer']),
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

    private function columnKeyForStatus(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_REVIEW, Order::STATUS_CONFIRMED => 'new',
            Order::STATUS_PREPARING => 'preparing',
            Order::STATUS_READY_FOR_DISPATCH => 'ready',
            Order::STATUS_DISPATCHED => 'dispatched',
            default => 'new',
        };
    }
}
