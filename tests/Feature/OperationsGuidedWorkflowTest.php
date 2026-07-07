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
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsGuidedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_review_shows_confirmar_pedido_and_not_iniciar_preparacion(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_PENDING_REVIEW);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Confirmar pedido')
            ->assertDontSee('Iniciar preparación');
    }

    public function test_confirmed_shows_iniciar_preparacion_and_not_confirmar_pedido(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_CONFIRMED);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Iniciar preparación')
            ->assertDontSee('Confirmar pedido');
    }

    public function test_preparing_shows_marcar_listo_and_not_confirmar_pedido(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_PREPARING);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Marcar listo')
            ->assertDontSee('Confirmar pedido');
    }

    public function test_ready_for_dispatch_shows_despachar(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_READY_FOR_DISPATCH);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Despachar');
    }

    public function test_dispatched_shows_no_transition_cta(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_DISPATCHED);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Pedido despachado')
            ->assertSee('Ver historial')
            ->assertDontSee('Confirmar pedido')
            ->assertDontSee('Iniciar preparación')
            ->assertDontSee('Marcar listo')
            ->assertDontSee('Despachar');
    }

    public function test_cancelled_and_rejected_show_no_transition_cta(): void
    {
        [$cancelledUser, $cancelledOrder] = $this->makeWorkflowOrder(Order::STATUS_CANCELLED, 'Pedido cancelado');
        [$rejectedUser, $rejectedOrder] = $this->makeWorkflowOrder(Order::STATUS_REJECTED, 'Pedido rechazado');

        $this->actingAs($cancelledUser)
            ->get(route('operations.index', ['order' => $cancelledOrder->id]))
            ->assertOk()
            ->assertSee('Pedido cancelado')
            ->assertDontSee('Confirmar pedido')
            ->assertDontSee('Iniciar preparación')
            ->assertDontSee('Marcar listo')
            ->assertDontSee('Despachar');

        $this->actingAs($rejectedUser)
            ->get(route('operations.index', ['order' => $rejectedOrder->id]))
            ->assertOk()
            ->assertSee('Pedido rechazado')
            ->assertDontSee('Confirmar pedido')
            ->assertDontSee('Iniciar preparación')
            ->assertDontSee('Marcar listo')
            ->assertDontSee('Despachar');
    }

    public function test_invalid_transition_still_returns_422_from_backend(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_PENDING_REVIEW);

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertStatus(422);
    }

    public function test_json_transition_returns_updated_status_and_next_actions(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_PENDING_REVIEW);

        $this->actingAs($user)
            ->postJson(route('orders.confirm', $order))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.status', Order::STATUS_CONFIRMED)
            ->assertJsonPath('order.status_label', 'Confirmado')
            ->assertJsonPath('order.status_tone', 'bg-blue-50 text-blue-800 ring-1 ring-blue-100')
            ->assertJsonPath('order.allowed_actions.0.key', 'prepare')
            ->assertJsonPath('next_actions.0.key', 'prepare');
    }

    public function test_operations_page_does_not_render_invalid_actions_for_current_status(): void
    {
        [$user, $order] = $this->makeWorkflowOrder(Order::STATUS_PREPARING);

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Marcar listo')
            ->assertDontSee('Confirmar pedido')
            ->assertDontSee('Iniciar preparación');
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeWorkflowOrder(string $status, ?string $terminalMessage = null): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Guided Workflow Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Guided Workflow Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@guided-workflow-' . Str::slug($status),
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Guided Workflow Admin',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Workflow Customer',
                'phone' => '+50255501234',
                'external_id' => null,
            ]);

            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Producto guiado',
                'sku' => 'GUIDED-01',
                'unit_label' => 'unidad',
                'is_active' => true,
                'sort_order' => 0,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:05:00'));
            $order = Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => fake()->uuid(),
                'status' => $status,
                'parser_confidence' => 0.95,
                'raw_message_text' => 'Pedido de workflow guiado',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => fake()->uuid(),
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => in_array($status, [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED, Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true) ? now() : null,
                'confirmed_by' => null,
                'confirmed_at' => in_array($status, [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
                'preparing_at' => in_array($status, [Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
                'ready_for_dispatch_at' => in_array($status, [Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED], true) ? now() : null,
                'dispatched_at' => $status === Order::STATUS_DISPATCHED ? now() : null,
                'cancelled_at' => $status === Order::STATUS_CANCELLED ? now() : null,
                'rejected_at' => $status === Order::STATUS_REJECTED ? now() : null,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit' => 'unidad',
                'raw_text' => 'Pedido de workflow guiado',
                'matched_text' => 'Pedido de workflow guiado',
                'confidence_score' => 0.95,
                'notes' => null,
                'sort_order' => 0,
            ]);

            return [$user->fresh(), $order->fresh(['customer', 'orderItems.product', 'branch'])];
        } finally {
            Carbon::setTestNow();
        }
    }
}
