<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FulfillmentPlan;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Fulfillment\FulfillmentDecisionEngine;
use App\Services\Fulfillment\FulfillmentPlannerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class FulfillmentDecisionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 30, 0, config('app.timezone', 'UTC')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_delivery_vip_maps_to_urgent_priority(): void
    {
        $previousVipMinOrders = Config::get('fulfillment.priority.vip_min_orders');
        Config::set('fulfillment.priority.vip_min_orders', 2);

        try {
            [$organization, $branch, $customer] = $this->makeContext();
            $this->makeOrder($organization, $branch, $customer, 'Pedido previo');

            $order = $this->makeOrder($organization, $branch, $customer, 'Me lo llevan hoy por delivery');
            $plan = $this->planner()->parseIntentFromMessage($order, 'Me lo llevan hoy por delivery');

            $this->assertSame(95, $plan->priority_score);
            $this->assertSame('urgent', $plan->priority_level);
            $this->assertSame('2026-07-10', $plan->commitment_date?->toDateString());
            $this->assertTrue(Str::startsWith((string) $plan->commitment_time, '17:00'));
            $this->assertSame(270, $plan->remaining_sla_minutes);
            $this->assertSame('low', $plan->risk_level);
            $this->assertSame('v1', $plan->decision_version);
        } finally {
            Config::set('fulfillment.priority.vip_min_orders', $previousVipMinOrders);
        }
    }

    public function test_tomorrow_pickup_defaults_to_normal_priority_and_anytime_commitment(): void
    {
        [$organization, $branch, $customer] = $this->makeContext();

        $order = $this->makeOrder($organization, $branch, $customer, 'Yo paso por ellos para manana');
        $plan = $this->planner()->parseIntentFromMessage($order, 'Yo paso por ellos para manana');

        $this->assertSame('pickup', $plan->delivery_method);
        $this->assertSame(40, $plan->priority_score);
        $this->assertSame('normal', $plan->priority_level);
        $this->assertSame('2026-07-11', $plan->commitment_date?->toDateString());
        $this->assertTrue(Str::startsWith((string) $plan->commitment_time, '17:00'));
        $this->assertSame('low', $plan->risk_level);
    }

    public function test_explicit_time_before_2pm_sets_commitment_and_medium_risk(): void
    {
        [$organization, $branch, $customer] = $this->makeContext();

        $order = $this->makeOrder($organization, $branch, $customer, 'Me lo llevan hoy antes de las 2 pm');
        $plan = $this->planner()->parseIntentFromMessage($order, 'Me lo llevan hoy antes de las 2 pm');

        $this->assertSame('delivery', $plan->delivery_method);
        $this->assertSame(80, $plan->priority_score);
        $this->assertSame('high', $plan->priority_level);
        $this->assertSame('2026-07-10', $plan->commitment_date?->toDateString());
        $this->assertTrue(Str::startsWith((string) $plan->commitment_time, '14:00'));
        $this->assertSame(90, $plan->remaining_sla_minutes);
        $this->assertSame('medium', $plan->risk_level);
        $this->assertStringContainsString('Compromiso para hoy', (string) $plan->risk_reason);
    }

    public function test_commitment_defaults_follow_configured_window_times(): void
    {
        [$organization, $branch, $customer] = $this->makeContext();

        $cases = [
            'morning' => ['morning', '11:00'],
            'afternoon' => ['afternoon', '16:00'],
            'evening' => ['evening', '19:00'],
            'anytime' => ['anytime', '17:00'],
        ];

        foreach ($cases as $label => [$requestedTimeWindow, $expectedTime]) {
            $order = $this->makeOrder($organization, $branch, $customer, 'Pedido base ' . $label);
            $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

            $plan->forceFill([
                'requested_date' => '2026-07-10',
                'requested_time_window' => $requestedTimeWindow,
                'delivery_method' => 'pickup',
                'metadata_json' => [],
            ])->save();

            $decision = $this->engine()->evaluate($plan->refresh());

            $this->assertSame('2026-07-10', $decision['commitment_date']);
            $this->assertTrue(Str::startsWith((string) $decision['commitment_time'], $expectedTime), $label);
        }
    }

    public function test_expired_sla_is_critical(): void
    {
        [$organization, $branch, $customer] = $this->makeContext();

        $order = $this->makeOrder($organization, $branch, $customer, 'Pedido base');
        $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

        $plan->forceFill([
            'commitment_date' => '2026-07-10',
            'commitment_time' => '09:00:00',
            'metadata_json' => [],
        ])->save();

        $decision = $this->engine()->evaluate($plan->refresh());

        $this->assertLessThan(0, $decision['remaining_sla_minutes']);
        $this->assertSame('critical', $decision['risk_level']);
        $this->assertStringContainsString('SLA vencido', $decision['risk_reason']);
    }

    public function test_low_priority_band_can_be_reached_with_confidence_modifier(): void
    {
        $previousEnabled = Config::get('fulfillment.priority.confidence.enabled');
        $previousWeight = Config::get('fulfillment.priority.confidence.weight');
        Config::set('fulfillment.priority.confidence.enabled', true);
        Config::set('fulfillment.priority.confidence.weight', 20);

        try {
            [$organization, $branch, $customer] = $this->makeContext();
            $order = $this->makeOrder($organization, $branch, $customer, 'Pedido sin urgencia');
            $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

            $plan->forceFill([
                'planner_confidence' => 0,
                'requested_date' => null,
                'requested_time_window' => null,
                'delivery_method' => 'pickup',
                'metadata_json' => [],
            ])->save();

            $decision = $this->engine()->evaluate($plan->refresh());

            $this->assertSame(20, $decision['priority_score']);
            $this->assertSame('low', $decision['priority_level']);
            $this->assertNull($decision['remaining_sla_minutes']);
            $this->assertSame('low', $decision['risk_level']);
        } finally {
            Config::set('fulfillment.priority.confidence.enabled', $previousEnabled);
            Config::set('fulfillment.priority.confidence.weight', $previousWeight);
        }
    }

    private function engine(): FulfillmentDecisionEngine
    {
        return app(FulfillmentDecisionEngine::class);
    }

    private function planner(): FulfillmentPlannerService
    {
        return app(FulfillmentPlannerService::class);
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: Customer}
     */
    private function makeContext(): array
    {
        $organization = Organization::create([
            'name' => 'Fulfillment Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@fulfillment-test',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Customer One',
            'phone' => '5550001',
            'external_id' => null,
        ]);

        return [$organization, $branch, $customer];
    }

    private function makeOrder(Organization $organization, Branch $branch, Customer $customer, string $rawMessageText): Order
    {
        return Order::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => 'telegram',
            'external_message_id' => null,
            'status' => Order::STATUS_PENDING_REVIEW,
            'parser_confidence' => 0.95,
            'raw_message_text' => $rawMessageText,
            'parsed_payload_json' => ['items' => []],
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
    }

}
