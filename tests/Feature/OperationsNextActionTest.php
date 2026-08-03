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

class OperationsNextActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_operations_page_renders_the_next_action_home(): void
    {
        [$user] = $this->makeNextActionFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Benditio Operations Center')
            ->assertSeeText('Today')
            ->assertSeeText('DO NOW')
            ->assertSeeText('NEXT')
            ->assertSeeText('COMPLETED')
            ->assertSee('snapshotUrlBase', false);
    }

    public function test_next_action_feed_builds_do_now_next_and_completed_sections(): void
    {
        [$user, $fixture] = $this->makeNextActionFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $sections = collect($payload['next_action_queue']['sections'] ?? [])->keyBy('key');
        $doNowCards = collect($sections->get('do_now', ['cards' => []])['cards'] ?? []);
        $nextGroups = collect($sections->get('next', ['groups' => []])['groups'] ?? []);
        $completedCards = collect($sections->get('completed', ['cards' => []])['cards'] ?? []);

        $this->assertCount(5, $doNowCards);
        $this->assertSame(-20, (int) data_get($doNowCards->first(), 'remaining_sla_minutes'));
        $this->assertNotEmpty($nextGroups);
        $this->assertTrue($nextGroups->pluck('label')->contains('Tomorrow'));
        $this->assertNotNull($sections->get('completed'));
    }

    public function test_completed_section_keeps_dispatches_even_when_commitment_is_tomorrow(): void
    {
        [$user, $fixture] = $this->makeNextActionFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $completedCards = collect(data_get($payload, 'next_action_queue.sections', []))
            ->firstWhere('key', 'completed');
        $cards = collect(data_get($completedCards, 'cards', []));

        $this->assertTrue(
            $cards->contains(fn (array $card): bool => (int) ($card['id'] ?? 0) === $fixture['dispatchedFutureCommitmentOrder']->id),
        );
        $this->assertSame(
            'dispatched',
            data_get($cards->firstWhere('id', $fixture['dispatchedFutureCommitmentOrder']->id), 'status'),
        );
    }

    public function test_do_now_ordering_prioritizes_the_most_urgent_cards(): void
    {
        [$user] = $this->makeNextActionFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $doNowCards = collect(data_get($payload, 'next_action_queue.sections', []))
            ->firstWhere('key', 'do_now');

        $cards = collect(data_get($doNowCards, 'cards', []));

        $this->assertSame(-20, (int) data_get($cards->get(0), 'remaining_sla_minutes'));
        $this->assertSame(25, (int) data_get($cards->get(1), 'remaining_sla_minutes'));
    }

    public function test_snapshot_drawer_contains_advanced_context(): void
    {
        [$user, $fixture] = $this->makeNextActionFixture();

        $this->actingAs($user)
            ->getJson(route('operations.orders.snapshot', $fixture['todayMorningOrder']))
            ->assertOk()
            ->assertJsonStructure([
                'parser_details',
                'fulfillment_plan',
                'duplicate_analysis',
                'timeline',
                'notification_history',
            ])
            ->assertJsonPath('parser_details.decision_version', 'v1')
            ->assertJsonPath('fulfillment_plan.delivery_method', 'pickup');
    }

    public function test_organization_isolation_hides_foreign_orders_from_the_queue(): void
    {
        [$user, $fixture] = $this->makeNextActionFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $customerNames = collect($payload['inbox'] ?? [])->pluck('customer_name')->all();

        $this->assertContains($fixture['todayMorningOrder']->customer->name, $customerNames);
        $this->assertNotContains($fixture['foreignOrder']->customer->name, $customerNames);
    }

    /**
     * @return array{0: User, 1?: array<string, mixed>}
     */
    private function makeNextActionFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 08:00:00'));

        $organization = Organization::create([
                'name' => 'Next Action Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Next Action Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@next-action',
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

            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Queue Product',
                'sku' => 'QUEUE-01',
                'unit_label' => 'unidad',
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Queue Customer',
                'phone' => '+50255550000',
                'external_id' => null,
            ]);

            $criticalOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'raw_message_text' => 'critical order',
                'remaining_sla_minutes' => -20,
                'risk_level' => 'critical',
                'priority_level' => 'urgent',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '08:15:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 92,
            ]);

            $dueSoonOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'due soon order',
                'remaining_sla_minutes' => 25,
                'risk_level' => 'high',
                'priority_level' => 'urgent',
                'delivery_method' => 'pickup',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '09:30:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 90,
            ]);

            $todayMorningOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_PREPARING,
                'raw_message_text' => 'today morning',
                'remaining_sla_minutes' => 3000,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '08:45:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 88,
            ]);

            $todayAfternoonOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_READY_FOR_DISPATCH,
                'raw_message_text' => 'today afternoon',
                'remaining_sla_minutes' => 220,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'delivery',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '14:30:00',
                'requested_time_window' => 'afternoon',
                'decision_version' => 'v1',
                'planner_confidence' => 85,
            ]);

            $tomorrowOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'tomorrow order',
                'remaining_sla_minutes' => 1440,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-11',
                'commitment_time' => '15:00:00',
                'requested_time_window' => 'afternoon',
                'decision_version' => 'v1',
                'planner_confidence' => 84,
            ]);

            $noCommitmentOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'no commitment order',
                'remaining_sla_minutes' => null,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => null,
                'commitment_time' => null,
                'requested_time_window' => null,
                'decision_version' => 'v1',
                'planner_confidence' => 82,
            ]);

            $lateMorningOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'late morning order',
                'remaining_sla_minutes' => 2000,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '09:45:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 81,
            ]);

            $completedOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_DISPATCHED,
                'raw_message_text' => 'completed order',
                'remaining_sla_minutes' => 60,
                'risk_level' => 'low',
                'priority_level' => 'low',
                'delivery_method' => 'delivery',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '10:30:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 80,
            ]);

            $dispatchedFutureCommitmentOrder = $this->createOrder($customer, $branch, $product, [
                'status' => Order::STATUS_DISPATCHED,
                'raw_message_text' => 'dispatched future commitment',
                'remaining_sla_minutes' => 45,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-11',
                'commitment_time' => '10:45:00',
                'requested_time_window' => 'morning',
                'decision_version' => 'v1',
                'planner_confidence' => 79,
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
                'external_message_id' => fake()->uuid(),
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => 0.88,
                'raw_message_text' => 'foreign order',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => fake()->uuid(),
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
                'criticalOrder' => $criticalOrder->fresh(['customer']),
                'dueSoonOrder' => $dueSoonOrder->fresh(['customer']),
                'todayMorningOrder' => $todayMorningOrder->fresh(['customer']),
                'todayAfternoonOrder' => $todayAfternoonOrder->fresh(['customer']),
                'tomorrowOrder' => $tomorrowOrder->fresh(['customer']),
                'noCommitmentOrder' => $noCommitmentOrder->fresh(['customer']),
                'lateMorningOrder' => $lateMorningOrder->fresh(['customer']),
                'completedOrder' => $completedOrder->fresh(['customer']),
                'dispatchedFutureCommitmentOrder' => $dispatchedFutureCommitmentOrder->fresh(['customer']),
                'foreignOrder' => Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
            ],
        ];
    }

    private function createOrder(Customer $customer, Branch $branch, Product $product, array $attributes): Order
    {
        $order = Order::create([
            'organization_id' => $customer->organization_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'possible_duplicate_of_order_id' => null,
            'source_channel' => 'telegram',
            'external_message_id' => fake()->uuid(),
            'status' => $attributes['status'],
            'parser_confidence' => 0.95,
            'raw_message_text' => $attributes['raw_message_text'],
            'parsed_payload_json' => ['items' => []],
            'duplicate_score' => null,
            'duplicate_reason' => null,
            'duplicate_checked_at' => now(),
            'order_fingerprint' => fake()->uuid(),
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => $attributes['status'] !== Order::STATUS_PENDING_REVIEW ? now() : null,
            'confirmed_by' => null,
            'confirmed_at' => in_array($attributes['status'], [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'preparing_at' => in_array($attributes['status'], [Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'ready_for_dispatch_at' => in_array($attributes['status'], [Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'dispatched_at' => $attributes['status'] === Order::STATUS_DISPATCHED ? now() : null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'unidad',
            'raw_text' => $attributes['raw_message_text'],
            'matched_text' => $attributes['raw_message_text'],
            'confidence_score' => 0.95,
            'notes' => null,
            'sort_order' => 0,
        ]);

        $order->fulfillmentPlan->forceFill([
            'delivery_method' => $attributes['delivery_method'],
            'payment_method' => $attributes['payment_method'],
            'commitment_date' => $attributes['commitment_date'],
            'commitment_time' => $attributes['commitment_time'],
            'remaining_sla_minutes' => $attributes['remaining_sla_minutes'],
            'risk_level' => $attributes['risk_level'],
            'priority_level' => $attributes['priority_level'],
            'requested_time_window' => $attributes['requested_time_window'] ?? null,
            'decision_version' => $attributes['decision_version'] ?? null,
            'planner_confidence' => $attributes['planner_confidence'] ?? null,
            'planner_notes' => $attributes['planner_notes'] ?? null,
        ])->save();

        return $order->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']);
    }
}
