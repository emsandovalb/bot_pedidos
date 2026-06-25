<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_analytics_page(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk();
    }

    public function test_analytics_page_shows_top_kpi_cards(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSeeText('Pedidos hoy')
            ->assertSeeText('Pedidos últimos 7 días')
            ->assertSeeText('Pedidos este mes')
            ->assertSeeText('Despachados este mes')
            ->assertSeeText('Pendientes de revisión');
    }

    public function test_analytics_page_shows_order_status_counts(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSeeText('Pedidos por estado')
            ->assertSeeText('Pendiente de revisión')
            ->assertSeeText('Confirmado')
            ->assertSeeText('En preparación')
            ->assertSeeText('Listo para despacho')
            ->assertSeeText('Despachado')
            ->assertSeeText('Cancelado')
            ->assertSeeText('Rechazado');
    }

    public function test_analytics_page_shows_top_requested_products(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSeeText('Productos más solicitados')
            ->assertSeeText('Bolsas de jardín')
            ->assertSeeText('2 bolsas de arena');
    }

    public function test_analytics_page_shows_frequent_customers(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSeeText('Clientes frecuentes')
            ->assertSeeText('Cliente A')
            ->assertSeeText('Cliente B');
    }

    public function test_analytics_page_shows_classification_percentage(): void
    {
        [$user] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSeeText('Clasificación automática')
            ->assertSeeText('86%')
            ->assertSeeText('Con producto')
            ->assertSeeText('Sin producto');
    }

    public function test_analytics_page_links_to_recent_orders(): void
    {
        [$user, , , $latestOrder] = $this->seedAnalyticsScenario();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee(route('orders.show', $latestOrder));
    }

    /**
     * @return array{0: User, 1: Organization, 2: Branch, 3: Order}
     */
    private function seedAnalyticsScenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Analytics Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Analytics Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@analytics-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Analytics Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        $productA = Product::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Bolsas de jardín',
            'sku' => 'SKU-A',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $productB = Product::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Cajas de vasos',
            'sku' => 'SKU-B',
            'unit_label' => 'caja',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $customerA = Customer::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Cliente A',
            'phone' => '555-1001',
            'external_id' => null,
        ]);

        $customerB = Customer::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Cliente B',
            'phone' => '555-1002',
            'external_id' => null,
        ]);

        $today = today();

        $latestOrder = $this->makeOrder($organization, $branch, $customerA, Order::STATUS_CONFIRMED, $today->copy()->setTime(14, 0), 'whatsapp', 'Pedido confirmado de la tarde', [
            ['product_id' => $productA->id, 'quantity' => 3, 'raw_text' => '3 bolsas de jardín', 'matched_text' => 'bolsas de jardín'],
        ]);

        $this->makeOrder($organization, $branch, $customerA, Order::STATUS_PENDING_REVIEW, $today->copy()->setTime(10, 0), 'telegram', 'Pedido pendiente de revisión', [
            ['product_id' => $productA->id, 'quantity' => 2, 'raw_text' => '2 bolsas de jardín', 'matched_text' => 'bolsas de jardín'],
        ]);

        $this->makeOrder($organization, $branch, $customerB, Order::STATUS_PREPARING, $today->copy()->subDays(1)->setTime(13, 0), 'telegram', 'Pedido en preparación', [
            ['product_id' => $productB->id, 'quantity' => 4, 'raw_text' => '4 cajas de vasos', 'matched_text' => 'cajas de vasos'],
        ]);

        $this->makeOrder($organization, $branch, $customerB, Order::STATUS_READY_FOR_DISPATCH, $today->copy()->subDays(2)->setTime(11, 0), 'whatsapp', 'Pedido listo', [
            ['product_id' => null, 'quantity' => 2, 'raw_text' => '2 bolsas de arena', 'matched_text' => null],
        ]);

        $this->makeOrder($organization, $branch, $customerA, Order::STATUS_DISPATCHED, $today->copy()->subDays(3)->setTime(9, 0), 'telegram', 'Pedido despachado', [
            ['product_id' => $productB->id, 'quantity' => 1, 'raw_text' => '1 caja de vasos', 'matched_text' => 'cajas de vasos'],
        ], $today->copy()->subDays(3)->setTime(12, 15));

        $this->makeOrder($organization, $branch, $customerB, Order::STATUS_CANCELLED, $today->copy()->subDays(4)->setTime(15, 0), 'telegram', 'Pedido cancelado', [
            ['product_id' => $productA->id, 'quantity' => 1, 'raw_text' => '1 bolsa de jardín', 'matched_text' => 'bolsas de jardín'],
        ]);

        $this->makeOrder($organization, $branch, $customerA, Order::STATUS_REJECTED, $today->copy()->subDays(5)->setTime(16, 0), 'telegram', 'Pedido rechazado', [
            ['product_id' => $productA->id, 'quantity' => 1, 'raw_text' => '1 bolsa de jardín', 'matched_text' => 'bolsas de jardín'],
        ]);

        return [$user->fresh(), $organization->fresh(), $branch->fresh(), $latestOrder->fresh()->load('customer')];
    }

    /**
     * @param  array<int, array{product_id:?int, quantity:int, raw_text:?string, matched_text:?string}>  $items
     */
    private function makeOrder(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $status,
        Carbon $createdAt,
        string $sourceChannel,
        string $rawMessageText,
        array $items,
        ?Carbon $dispatchedAt = null,
    ): Order {
        $order = Order::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => $sourceChannel,
            'external_message_id' => null,
            'status' => $status,
            'parser_confidence' => 0.98,
            'raw_message_text' => $rawMessageText,
            'parsed_payload_json' => ['items' => []],
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => null,
            'ready_for_dispatch_at' => null,
            'dispatched_at' => $dispatchedAt,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'dispatched_at' => $dispatchedAt,
            ]);

        foreach ($items as $index => $item) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['product_id'] ? 'unidad' : 'unidad',
                'raw_text' => $item['raw_text'],
                'matched_text' => $item['matched_text'],
                'confidence_score' => $item['product_id'] ? 0.95 : null,
                'notes' => null,
                'sort_order' => $index,
            ]);
        }

        return $order->fresh()->load('customer');
    }
}
