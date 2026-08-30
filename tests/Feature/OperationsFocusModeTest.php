<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Services\Operations\FocusModePresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OperationsFocusModeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_access_the_focus_page(): void
    {
        [$user] = $this->makeFocusFixture();

        $this->actingAs($user)
            ->get(route('operations.focus'))
            ->assertOk()
            ->assertSeeText('Mi Jornada')
            ->assertSeeText('Tu siguiente pedido');
    }

    public function test_organization_isolation_is_enforced(): void
    {
        [$user, $fixture] = $this->makeFocusFixture();

        $this->actingAs($user)
            ->get(route('operations.focus'))
            ->assertOk()
            ->assertDontSeeText($fixture['foreignOrder']->customer->name);
    }

    public function test_recommended_order_uses_next_action_queue_ordering(): void
    {
        [$user, $fixture] = $this->makeFocusFixture();

        $this->actingAs($user);
        $request = Request::create('/operations/focus', 'GET');

        $payload = app(FocusModePresenter::class)->build($request);

        $this->assertSame($fixture['urgentOrder']->id, data_get($payload, 'recommendedOrder.id'));
        $this->assertSame('REVIEW', data_get($payload, 'recommendedOrder.focus_state'));
        $this->assertSame('Revisar pedido', data_get($payload, 'recommendedOrder.recommended_action_label'));
        $this->assertSame('Confirmar pedido', data_get($payload, 'recommendedOrder.workspace_action_label'));
        $this->assertSame('miércoles 5 de agosto a las 1:30 p. m.', data_get($payload, 'recommendedOrder.commitment_label'));
        $this->assertSame('miércoles 5 de agosto', data_get($payload, 'recommendedOrder.fulfillment.date'));
        $this->assertSame('1:30 p. m.', data_get($payload, 'recommendedOrder.fulfillment.time'));
    }

    public function test_friendly_labels_are_exposed_without_technical_status_names(): void
    {
        [$user] = $this->makeFocusFixture();

        $this->actingAs($user);

        $payload = app(FocusModePresenter::class)->build(Request::create('/operations/focus', 'GET'));

        $this->assertSame('Mi Jornada', data_get($payload, 'visibleName'));
        $this->assertSame('¿Qué debo hacer ahora?', data_get($payload, 'mainQuestion'));
        $this->assertSame('Entrega', data_get($payload, 'recommendedOrder.delivery_label'));
        $this->assertSame('SINPE', data_get($payload, 'recommendedOrder.payment_label'));
        $this->assertSame('WhatsApp', data_get($payload, 'recommendedOrder.channel_label'));
        $this->assertSame('Urgente', data_get($payload, 'recommendedOrder.priority_label'));
        $this->assertSame('miércoles 5 de agosto a las 1:30 p. m.', data_get($payload, 'recommendedOrder.commitment_display.label'));

        $this->actingAs($user)
            ->get(route('operations.focus'))
            ->assertOk()
            ->assertSee('data-focus-inline-review')
            ->assertDontSee('role="dialog"')
            ->assertDontSeeText('pending_review')
            ->assertDontSeeText('preparing')
            ->assertDontSeeText('ready_for_dispatch')
            ->assertDontSeeText('Sin fecha confirmada');
    }

    public function test_completed_and_no_work_states_are_supported(): void
    {
        [$user] = $this->makeNoWorkFixture();

        $this->actingAs($user)
            ->get(route('operations.focus'))
            ->assertOk()
            ->assertSeeText('Excelente.')
            ->assertSeeText('No quedan pedidos pendientes.')
            ->assertSeeText('Actualizar')
            ->assertSeeText('Ir a Operaciones');

        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

        try {
            $presenterPayload = app(FocusModePresenter::class)->build(Request::create('/operations/focus', 'GET'));

            $this->assertNull(data_get($presenterPayload, 'recommendedOrder'));
            $this->assertSame(1, data_get($presenterPayload, 'completedSummary.count'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_day_summary_renders(): void
    {
        [$user] = $this->makeFocusFixture();

        $this->actingAs($user)
            ->get(route('operations.focus'))
            ->assertOk()
            ->assertSeeText('Pedidos para hoy')
            ->assertSeeText('Urgentes')
            ->assertSeeText('Entregas')
            ->assertSeeText('Retiros');
    }

    public function test_upcoming_orders_render(): void
    {
        [$user, $fixture] = $this->makeFocusFixture();

        $this->actingAs($user);

        $payload = app(FocusModePresenter::class)->build(Request::create('/operations/focus', 'GET'));

        $this->assertSame([], data_get($payload, 'upcomingOrders'));
        $this->assertNull(data_get($payload, 'nextScheduledOrder'));
    }

    public function test_completed_summary_uses_dispatched_at(): void
    {
        [$user, $fixture] = $this->makeFocusFixture();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

        try {
            $payload = app(FocusModePresenter::class)->build(Request::create('/operations/focus', 'GET'));

            $this->assertSame(1, data_get($payload, 'completedSummary.count'));
            $this->assertSame($fixture['completedTodayOrder']->id, data_get($payload, 'completedSummary.orders.0.id'));
            $this->assertSame($fixture['completedTodayOrder']->customer->name, data_get($payload, 'completedSummary.orders.0.customer_name'));
            $this->assertNotContains(
                $fixture['completedYesterdayOrder']->customer->name,
                array_column(data_get($payload, 'completedSummary.orders', []), 'customer_name')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_current_operations_route_remains_available(): void
    {
        [$user] = $this->makeFocusFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Benditio Operations Center');
    }

    /**
     * @return array{0: User, 1?: array<string, mixed>}
     */
    private function makeFocusFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Focus Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Focus Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => '@focus',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Emmanuel',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $hammer = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Martillo',
                'sku' => 'HAM-01',
                'unit_label' => 'pieza',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            $nails = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Clavos',
                'sku' => 'NAI-01',
                'unit_label' => 'caja',
                'is_active' => true,
                'sort_order' => 2,
            ]);

            $drill = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Taladro',
                'sku' => 'DRL-01',
                'unit_label' => 'pieza',
                'is_active' => true,
                'sort_order' => 3,
            ]);

            $paint = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Pintura',
                'sku' => 'PNT-01',
                'unit_label' => 'galón',
                'is_active' => true,
                'sort_order' => 4,
            ]);

            $tape = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Cinta',
                'sku' => 'TAP-01',
                'unit_label' => 'pieza',
                'is_active' => true,
                'sort_order' => 5,
            ]);

            $urgentCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Urgente',
                'phone' => '+50255550001',
                'external_id' => null,
            ]);

            $tomorrowCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Mañana',
                'phone' => '+50255550002',
                'external_id' => null,
            ]);

            $preparingCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Preparando',
                'phone' => '+50255550003',
                'external_id' => null,
            ]);

            $completedTodayCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Completado Hoy',
                'phone' => '+50255550004',
                'external_id' => null,
            ]);

            $completedYesterdayCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Completado Ayer',
                'phone' => '+50255550005',
                'external_id' => null,
            ]);

            $urgentOrder = $this->createOrder($urgentCustomer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'Necesito herramientas para hoy a las 2 pm y pago por SINPE',
                'delivery_method' => 'delivery',
                'payment_method' => 'sinpe',
                'commitment_date' => '2026-08-05',
                'commitment_time' => '13:30:00',
                'remaining_sla_minutes' => -15,
                'risk_level' => 'critical',
                'priority_level' => 'urgent',
                'parser_confidence' => 0.97,
            ], [
                ['product' => $hammer, 'quantity' => 1, 'unit' => 'pieza'],
                ['product' => $nails, 'quantity' => 2, 'unit' => 'caja'],
                ['product' => $drill, 'quantity' => 1, 'unit' => 'pieza'],
                ['product' => $tape, 'quantity' => 3, 'unit' => 'pieza'],
            ]);

            $tomorrowOrder = $this->createOrder($tomorrowCustomer, $branch, [
                'status' => Order::STATUS_CONFIRMED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'Pintura para mañana por la mañana',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-08-06',
                'commitment_time' => '08:30:00',
                'remaining_sla_minutes' => 1440,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.94,
            ], [
                ['product' => $paint, 'quantity' => 2, 'unit' => 'galón'],
            ]);

            $preparingOrder = $this->createOrder($preparingCustomer, $branch, [
                'status' => Order::STATUS_PREPARING,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'Pedido en preparación para hoy',
                'delivery_method' => 'delivery',
                'payment_method' => 'transfer',
                'commitment_date' => '2026-08-05',
                'commitment_time' => '15:00:00',
                'remaining_sla_minutes' => 220,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.93,
            ], [
                ['product' => $hammer, 'quantity' => 1, 'unit' => 'pieza'],
            ]);

            $completedTodayOrder = $this->createOrder($completedTodayCustomer, $branch, [
                'status' => Order::STATUS_DISPATCHED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'Pedido completado hoy',
                'delivery_method' => 'delivery',
                'payment_method' => 'card',
                'commitment_date' => '2026-08-05',
                'commitment_time' => '10:30:00',
                'remaining_sla_minutes' => 60,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.91,
            ], [
                ['product' => $nails, 'quantity' => 1, 'unit' => 'caja'],
            ]);

            $completedYesterdayOrder = $this->createOrder($completedYesterdayCustomer, $branch, [
                'status' => Order::STATUS_DISPATCHED,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'Pedido completado ayer',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-08-04',
                'commitment_time' => '11:00:00',
                'remaining_sla_minutes' => 90,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.90,
                'dispatched_at' => Carbon::parse('2026-08-04 17:15:00'),
            ], [
                ['product' => $tape, 'quantity' => 1, 'unit' => 'pieza'],
            ]);

            $foreignOrganization = Organization::create([
                'name' => 'Foreign Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $foreignBranch = Branch::create([
                'organization_id' => $foreignOrganization->id,
                'name' => 'Foreign Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@foreign',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $foreignCustomer = Customer::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'name' => 'Cliente Externo',
                'phone' => '+50255550999',
                'external_id' => null,
            ]);

            Order::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'customer_id' => $foreignCustomer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => fake()->uuid(),
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => 0.88,
                'raw_message_text' => 'Pedido externo',
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
                    'urgentOrder' => $urgentOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'tomorrowOrder' => $tomorrowOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'preparingOrder' => $preparingOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'completedTodayOrder' => $completedTodayOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'completedYesterdayOrder' => $completedYesterdayOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'foreignOrder' => Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
                ],
            ];
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{0: User}
     */
    private function makeNoWorkFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'No Work Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'No Work Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => '@nowork',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Emmanuel',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Cemento',
                'sku' => 'CEM-01',
                'unit_label' => 'saco',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            $completedTodayCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Completado Hoy',
                'phone' => '+50255551111',
                'external_id' => null,
            ]);

            $completedYesterdayCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Cliente Completado Ayer',
                'phone' => '+50255552222',
                'external_id' => null,
            ]);

            $completedTodayOrder = $this->createOrder($completedTodayCustomer, $branch, [
                'status' => Order::STATUS_DISPATCHED,
                'source_channel' => 'telegram',
                'raw_message_text' => 'Pedido completado hoy',
                'delivery_method' => 'delivery',
                'payment_method' => 'cash',
                'commitment_date' => '2026-08-05',
                'commitment_time' => '10:30:00',
                'remaining_sla_minutes' => 60,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.91,
            ], [
                ['product' => $product, 'quantity' => 1, 'unit' => 'saco'],
            ]);

            $completedYesterdayOrder = $this->createOrder($completedYesterdayCustomer, $branch, [
                'status' => Order::STATUS_CANCELLED,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'Pedido cancelado ayer',
                'delivery_method' => 'pickup',
                'payment_method' => 'cash',
                'commitment_date' => '2026-08-04',
                'commitment_time' => '11:00:00',
                'remaining_sla_minutes' => 90,
                'risk_level' => 'low',
                'priority_level' => 'normal',
                'parser_confidence' => 0.90,
                'dispatched_at' => Carbon::parse('2026-08-04 17:15:00'),
            ], [
                ['product' => $product, 'quantity' => 1, 'unit' => 'saco'],
            ]);

            return [
                $user->fresh(),
                [
                    'completedTodayOrder' => $completedTodayOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                    'completedYesterdayOrder' => $completedYesterdayOrder->fresh(['customer', 'orderItems.product', 'fulfillmentPlan']),
                ],
            ];
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{product: Product, quantity: int|float, unit: string, notes?: string}>  $items
     */
    private function createOrder(Customer $customer, Branch $branch, array $attributes, array $items): Order
    {
        $status = $attributes['status'];

        $order = Order::create([
            'organization_id' => $customer->organization_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'possible_duplicate_of_order_id' => null,
            'source_channel' => $attributes['source_channel'],
            'external_message_id' => fake()->uuid(),
            'status' => $status,
            'parser_confidence' => $attributes['parser_confidence'],
            'raw_message_text' => $attributes['raw_message_text'],
            'parsed_payload_json' => ['items' => []],
            'duplicate_score' => null,
            'duplicate_reason' => null,
            'duplicate_checked_at' => now(),
            'order_fingerprint' => fake()->uuid(),
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => $status !== Order::STATUS_PENDING_REVIEW ? now() : null,
            'confirmed_by' => null,
            'confirmed_at' => in_array($status, [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'preparing_at' => in_array($status, [Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'ready_for_dispatch_at' => in_array($status, [Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
            'dispatched_at' => $attributes['dispatched_at'] ?? ($status === Order::STATUS_DISPATCHED ? now() : null),
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        foreach (array_values($items) as $index => $itemDefinition) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $itemDefinition['product']->id,
                'quantity' => $itemDefinition['quantity'],
                'unit' => $itemDefinition['unit'],
                'raw_text' => $attributes['raw_message_text'],
                'matched_text' => $attributes['raw_message_text'],
                'confidence_score' => $attributes['parser_confidence'],
                'notes' => $itemDefinition['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }

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
