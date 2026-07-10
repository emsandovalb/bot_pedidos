<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FulfillmentPlan;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FulfillmentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfillment_plan_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('fulfillment_plans'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'order_id'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'risk_level'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'risk_reason'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'remaining_sla_minutes'));
        $this->assertTrue(Schema::hasColumn('fulfillment_plans', 'decision_version'));
    }

    public function test_fulfillment_plan_is_created_with_order(): void
    {
        $order = $this->makeOrder();

        $this->assertDatabaseHas('fulfillment_plans', [
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
        ]);
    }

    public function test_fulfillment_plan_relationships_work(): void
    {
        $order = $this->makeOrder();
        $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

        $this->assertNotNull($plan);
        $this->assertSame($order->id, $plan->order_id);
        $this->assertSame($order->organization_id, $plan->organization_id);
        $this->assertSame($order->id, $plan->order->id);
        $this->assertSame($order->organization_id, $plan->organization->id);
    }

    public function test_fulfillment_plan_uses_default_values(): void
    {
        $order = $this->makeOrder();
        $plan = $order->fresh(['fulfillmentPlan'])?->fulfillmentPlan;

        $this->assertNotNull($plan);
        $this->assertSame((int) config('fulfillment.defaults.priority_score', 0), $plan->priority_score);
        $this->assertSame(config('fulfillment.defaults.priority_level', 'normal'), $plan->priority_level);
        $this->assertSame((int) config('fulfillment.defaults.sla_minutes', 0), $plan->sla_minutes);
        $this->assertSame(config('fulfillment.defaults.delivery_method', 'unknown'), $plan->delivery_method);
        $this->assertSame(config('fulfillment.defaults.payment_method', 'unknown'), $plan->payment_method);
        $this->assertSame(config('fulfillment.decision_version', 'v1'), $plan->decision_version);
        $this->assertNull($plan->requested_date);
        $this->assertNull($plan->requested_time_window);
        $this->assertNull($plan->commitment_date);
        $this->assertNull($plan->commitment_time);
        $this->assertNull($plan->remaining_sla_minutes);
        $this->assertNull($plan->risk_level);
        $this->assertNull($plan->risk_reason);
        $this->assertSame(0, $plan->planner_confidence);
        $this->assertSame([], $plan->metadata_json);
    }

    public function test_fulfillment_plan_scope_filters_by_organization(): void
    {
        $firstOrder = $this->makeOrder('Organization One', '1110001');
        $secondOrder = $this->makeOrder('Organization Two', '2220002');

        $plans = FulfillmentPlan::query()
            ->forOrganization($firstOrder->organization_id)
            ->get();

        $this->assertCount(1, $plans);
        $this->assertSame($firstOrder->id, $plans->first()->order_id);
        $this->assertNotSame($firstOrder->organization_id, $secondOrder->organization_id);
    }

    private function makeOrder(string $organizationName = 'Fulfillment Org', string $phone = '5550001'): Order
    {
        $organization = Organization::create([
            'name' => $organizationName,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@fulfillment-' . Str::slug($organizationName . '-' . $phone, '-'),
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Customer',
            'phone' => $phone,
            'external_id' => null,
        ]);

        return Order::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => 'telegram',
            'external_message_id' => null,
            'status' => Order::STATUS_PENDING_REVIEW,
            'parser_confidence' => 0.95,
            'raw_message_text' => '2 bolsas de jardin',
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
