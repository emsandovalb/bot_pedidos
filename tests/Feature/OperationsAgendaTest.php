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

class OperationsAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_renders_as_the_default_landing_view(): void
    {
        [$user] = $this->makeAgendaFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Agenda', false)
            ->assertSee('Kanban', false)
            ->assertSee('Production schedule', false)
            ->assertSee('Orders Today', false)
            ->assertSee('Average SLA Remaining', false);
    }

    public function test_feed_groups_orders_into_the_expected_agenda_sections(): void
    {
        [$user, $fixture] = $this->makeAgendaFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $sections = collect($payload['agenda']['sections'] ?? [])->keyBy('key');
        $criticalCards = collect(data_get($sections->get('critical', []), 'groups.0.cards', []));
        $todayGroups = collect(data_get($sections->get('today', []), 'groups', []))->pluck('label')->all();

        $this->assertCount(2, $criticalCards);
        $this->assertSame(-20, (int) data_get($criticalCards->get(0), 'remaining_sla_minutes'));
        $this->assertSame(-5, (int) data_get($criticalCards->get(1), 'remaining_sla_minutes'));
        $this->assertSame(['Morning', 'Afternoon'], array_values($todayGroups));
        $this->assertNotNull($sections->get('tomorrow'));
        $this->assertNotNull($sections->get('completed'));
    }

    public function test_critical_orders_sort_before_due_soon_orders_and_by_remaining_sla(): void
    {
        [$user, $fixture] = $this->makeAgendaFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $criticalCards = collect(data_get(collect(data_get($payload, 'agenda.sections', []))->firstWhere('key', 'critical'), 'groups.0.cards', []));
        $dueSoonCards = collect(data_get(collect(data_get($payload, 'agenda.sections', []))->firstWhere('key', 'due_soon'), 'groups.0.cards', []));

        $this->assertCount(2, $criticalCards);
        $this->assertSame(-20, (int) data_get($criticalCards->get(0), 'remaining_sla_minutes'));
        $this->assertSame(-5, (int) data_get($criticalCards->get(1), 'remaining_sla_minutes'));
        $this->assertCount(1, $dueSoonCards);
        $this->assertSame(25, (int) data_get($dueSoonCards->get(0), 'remaining_sla_minutes'));
    }

    public function test_agenda_payload_respects_organization_isolation(): void
    {
        [$user, $fixture] = $this->makeAgendaFixture();

        $payload = $this->actingAs($user)
            ->getJson(route('operations.feed'))
            ->assertOk()
            ->json();

        $allNames = collect($payload['inbox'] ?? [])->pluck('customer_name')->all();

        $this->assertContains($fixture['todayMorningOrder']->customer->name, $allNames);
        $this->assertNotContains($fixture['foreignOrder']->customer->name, $allNames);
    }

    public function test_snapshot_drawer_hook_remains_available_from_the_agenda_page(): void
    {
        [$user, $fixture] = $this->makeAgendaFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $fixture['todayMorningOrder']->id]))
            ->assertOk()
            ->assertSee('snapshotUrlBase', false)
            ->assertSee('operations-select-order')
            ->assertSee('drawerLoading', false);
    }

    /**
     * @return array{0: User, 1: array<string, mixed>}
     */
    private function makeAgendaFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 08:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Agenda Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Agenda Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@agenda',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Agenda Owner',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Agenda Product',
                'sku' => 'AGENDA-01',
                'unit_label' => 'unidad',
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Agenda Customer',
                'phone' => '+50255551000',
                'external_id' => null,
            ]);

            $criticalFirst = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'raw_message_text' => 'critical first',
                'remaining_sla_minutes' => -20,
                'risk_level' => 'critical',
                'priority_level' => 'urgent',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '08:15:00',
            ]);

            $criticalSecond = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'raw_message_text' => 'critical second',
                'remaining_sla_minutes' => -5,
                'risk_level' => 'critical',
                'priority_level' => 'urgent',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '08:30:00',
            ]);

            $dueSoonOrder = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'due soon',
                'remaining_sla_minutes' => 25,
                'risk_level' => 'high',
                'priority_level' => 'urgent',
                'delivery_method' => 'pickup',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '09:30:00',
            ]);

            $todayMorningOrder = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_PREPARING,
                'raw_message_text' => 'today morning',
                'remaining_sla_minutes' => 180,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '08:45:00',
                'requested_time_window' => 'morning',
            ]);

            $todayAfternoonOrder = $this->createAgendaOrder($customer, $branch, $product, [
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
            ]);

            $tomorrowOrder = $this->createAgendaOrder($customer, $branch, $product, [
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
            ]);

            $noCommitmentOrder = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_CONFIRMED,
                'raw_message_text' => 'no commitment order',
                'remaining_sla_minutes' => null,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => null,
                'commitment_time' => null,
            ]);

            $completedOrder = $this->createAgendaOrder($customer, $branch, $product, [
                'status' => Order::STATUS_DISPATCHED,
                'raw_message_text' => 'completed today',
                'remaining_sla_minutes' => 60,
                'risk_level' => 'low',
                'priority_level' => 'low',
                'delivery_method' => 'delivery',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-07-10',
                'commitment_time' => '10:30:00',
            ]);

            $foreignOrganization = Organization::create([
                'name' => 'Foreign Agenda Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $foreignBranch = Branch::create([
                'organization_id' => $foreignOrganization->id,
                'name' => 'Foreign Agenda Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => '@foreign-agenda',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $foreignCustomer = Customer::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'name' => 'Foreign Agenda Customer',
                'phone' => '+50255559900',
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
                    'criticalFirst' => $criticalFirst->fresh(['customer']),
                    'criticalSecond' => $criticalSecond->fresh(['customer']),
                    'dueSoonOrder' => $dueSoonOrder->fresh(['customer']),
                    'todayMorningOrder' => $todayMorningOrder->fresh(['customer']),
                    'todayAfternoonOrder' => $todayAfternoonOrder->fresh(['customer']),
                    'tomorrowOrder' => $tomorrowOrder->fresh(['customer']),
                    'noCommitmentOrder' => $noCommitmentOrder->fresh(['customer']),
                    'completedOrder' => $completedOrder->fresh(['customer']),
                    'foreignOrder' => Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
                ],
            ];
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createAgendaOrder(Customer $customer, Branch $branch, Product $product, array $attributes): Order
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
        ])->save();

        return $order->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']);
    }
}
