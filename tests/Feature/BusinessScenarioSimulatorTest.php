<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessScenarioSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_business_scenario_sections(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertOk()
            ->assertSee('Business Scenario Simulator')
            ->assertSee('Business Scenarios')
            ->assertSee('Create Custom Customer Message')
            ->assertSee('Simulate Business Day');
    }

    public function test_generate_day_creates_realistic_orders_and_metrics(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_scenario',
                'scenario' => 'small_hardware_store',
            ])
            ->assertOk()
            ->assertSee('Generated scenario: Small Hardware Store.')
            ->assertSee('Avg parser confidence')
            ->assertSee('Avg priority score')
            ->assertSee('Delivery / Pickup');

        $this->assertSame(30, $this->businessOrderCount($user->organization_id));
        $this->assertSame(15, $this->businessCustomerCount($user->organization_id));
        $this->assertGreaterThan(0, $this->businessDuplicateCount($user->organization_id));
    }

    public function test_morning_rush_prioritizes_today_and_urgent_orders(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_scenario',
                'scenario' => 'morning_rush',
            ])
            ->assertOk();

        $orders = $this->businessOrders($user->organization_id);

        $this->assertSame(15, $orders->count());
        $this->assertGreaterThanOrEqual(
            10,
            $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->requested_date?->toDateString() ?? null) === today()->toDateString())->count(),
        );
        $this->assertGreaterThan(
            0,
            $orders->filter(fn (Order $order): bool => in_array($order->fulfillmentPlan?->priority_level, ['urgent', 'high'], true))->count(),
        );
    }

    public function test_construction_scenario_creates_large_delivery_orders(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_scenario',
                'scenario' => 'construction_company',
            ])
            ->assertOk();

        $orders = $this->businessOrders($user->organization_id);

        $this->assertSame(12, $orders->count());
        $this->assertGreaterThanOrEqual(
            10,
            $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->delivery_method ?? null) === 'delivery')->count(),
        );
        $this->assertGreaterThan(
            0,
            $orders->filter(fn (Order $order): bool => str_contains((string) $order->raw_message_text, 'bloques') || str_contains((string) $order->raw_message_text, 'cemento') || str_contains((string) $order->raw_message_text, 'pvc'))->count(),
        );
    }

    public function test_custom_message_injection_uses_real_pipeline(): void
    {
        [$user] = $this->prepareBusinessContext();

        $response = $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_custom_message',
                'provider' => 'whatsapp',
                'customer_mode' => 'new',
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255591234',
                'message' => 'Ocupo 20 bloques para manana temprano. Yo paso por ellos. Pago por SINPE.',
            ]);

        $response->assertOk()
            ->assertSee('Custom message injected through the production pipeline.');

        $this->assertGreaterThan(
            0,
            IncomingMessage::query()
                ->where('organization_id', $user->organization_id)
                ->where('provider', 'whatsapp')
                ->where('external_message_id', 'like', 'bizsim-custom-%')
                ->count(),
        );

        $this->assertGreaterThanOrEqual(1, $this->businessOrderCount($user->organization_id));
    }

    public function test_random_messages_create_requested_volume(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_random_messages',
                'business_count' => 25,
            ])
            ->assertOk()
            ->assertSee('Generated 25 random messages.');

        $this->assertSame(25, $this->businessOrderCount($user->organization_id));
    }

    public function test_business_day_simulation_creates_staggered_timestamps(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_day',
                'speed' => 5,
            ])
            ->assertOk()
            ->assertSee('Simulated business day at 5x speed.');

        $orders = $this->businessOrders($user->organization_id);

        $this->assertGreaterThan(0, $orders->count());
        $latest = $orders->sortBy('created_at')->last()?->created_at;
        $earliest = $orders->sortBy('created_at')->first()?->created_at;
        $this->assertGreaterThan(
            0,
            $latest !== null && $earliest !== null ? abs($latest->diffInMinutes($earliest)) : 0,
        );
    }

    public function test_cleanup_removes_only_generated_business_data(): void
    {
        [$user] = $this->prepareBusinessContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_scenario',
                'scenario' => 'small_hardware_store',
            ])
            ->assertOk();

        $branch = Branch::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', Branch::STATUS_ACTIVE)
            ->firstOrFail();

        $customer = Customer::query()->create([
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'name' => 'Real Customer',
            'phone' => '50255539999',
            'external_id' => null,
        ]);

        Order::query()->create([
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'possible_duplicate_of_order_id' => null,
            'source_channel' => 'whatsapp',
            'external_message_id' => 'real-order-1',
            'status' => Order::STATUS_PENDING_REVIEW,
            'parser_confidence' => 0.91,
            'raw_message_text' => '1 caja de clavos',
            'parsed_payload_json' => [],
            'duplicate_score' => null,
            'duplicate_reason' => null,
            'duplicate_checked_at' => now(),
            'order_fingerprint' => 'real-order-1',
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

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_reset',
                'business_reset_scope' => 'today',
            ])
            ->assertOk()
            ->assertSee('Cleanup completed');

        $this->assertSame(0, $this->businessOrderCount($user->organization_id));
        $this->assertSame(0, $this->businessIncomingMessageCount($user->organization_id));
        $this->assertDatabaseHas('orders', [
            'organization_id' => $user->organization_id,
            'external_message_id' => 'real-order-1',
        ]);
    }

    public function test_business_scenarios_are_isolated_per_organization(): void
    {
        [$firstUser] = $this->prepareBusinessContext('First Org');
        [$secondUser] = $this->prepareBusinessContext('Second Org');

        $this->actingAs($firstUser)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'business_scenario',
                'scenario' => 'farmers_market',
            ])
            ->assertOk();

        $this->assertSame(20, $this->businessOrderCount($firstUser->organization_id));
        $this->assertSame(0, $this->businessOrderCount($secondUser->organization_id));
    }

    /**
     * @return array{0: User, 1: ChannelConnection}
     */
    private function prepareBusinessContext(string $organizationName = 'Business Scenario Org'): array
    {
        config(['app.debug' => true]);

        $organization = Organization::query()->create([
            'name' => $organizationName,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Toolkit Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update([
            'owner_user_id' => $user->id,
        ]);

        Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Toolkit Branch',
            'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
            'channel_identifier' => 'toolkit-whatsapp-branch-' . $organization->id,
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertOk();

        return [$user->fresh(), $this->connectionFor($user)];
    }

    private function connectionFor(User $user): ChannelConnection
    {
        return ChannelConnection::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->firstOrFail();
    }

    private function businessOrders(int $organizationId)
    {
        return Order::query()
            ->where('organization_id', $organizationId)
            ->where('notes', 'like', '[business-scenario]%')
            ->with('fulfillmentPlan')
            ->orderBy('created_at')
            ->get();
    }

    private function businessOrderCount(int $organizationId): int
    {
        return $this->businessOrders($organizationId)->count();
    }

    private function businessCustomerCount(int $organizationId): int
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('external_id', 'like', 'business-scenario:customer:%')
            ->count();
    }

    private function businessDuplicateCount(int $organizationId): int
    {
        return Order::query()
            ->where('organization_id', $organizationId)
            ->where('notes', 'like', '[business-scenario]%')
            ->whereNotNull('possible_duplicate_of_order_id')
            ->count();
    }

    private function businessIncomingMessageCount(int $organizationId): int
    {
        return IncomingMessage::query()
            ->where('organization_id', $organizationId)
            ->where('external_message_id', 'like', 'bizsim-%')
            ->count();
    }
}
