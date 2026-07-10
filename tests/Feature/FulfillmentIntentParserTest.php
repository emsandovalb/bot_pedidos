<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Fulfillment\FulfillmentIntentParser;
use App\Services\Fulfillment\FulfillmentPlannerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentIntentParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 10, 10, 0, 0, config('app.timezone', 'UTC')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_detects_today(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Lo ocupo hoy');

        $this->assertSame('2026-07-10', $intent->requested_date);
    }

    public function test_detects_tomorrow(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Lo necesito para manana');

        $this->assertSame('2026-07-11', $intent->requested_date);
    }

    public function test_detects_day_after_tomorrow(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Lo necesito pasado manana');

        $this->assertSame('2026-07-12', $intent->requested_date);
    }

    public function test_detects_nearest_future_weekday(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Para el lunes');

        $this->assertSame('2026-07-13', $intent->requested_date);
    }

    public function test_detects_explicit_date(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Para 10/07/2026');

        $this->assertSame('2026-07-10', $intent->requested_date);
    }

    public function test_detects_morning(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Por la manana, por favor');

        $this->assertSame('morning', $intent->requested_time_window);
    }

    public function test_detects_afternoon(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'En la tarde');

        $this->assertSame('afternoon', $intent->requested_time_window);
    }

    public function test_detects_after_work(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Despues del trabajo');

        $this->assertSame('after_work', $intent->requested_time_window);
    }

    public function test_detects_pickup(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Yo paso por ellos');

        $this->assertSame('pickup', $intent->delivery_method);
    }

    public function test_detects_delivery(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Mandemelos a domicilio');

        $this->assertSame('delivery', $intent->delivery_method);
    }

    public function test_detects_express(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Lo necesito express');

        $this->assertSame('express', $intent->delivery_method);
    }

    public function test_detects_sinpe(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Pago por SINPE movil');

        $this->assertSame('sinpe', $intent->payment_method);
    }

    public function test_detects_cash(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Pago en efectivo');

        $this->assertSame('cash', $intent->payment_method);
    }

    public function test_detects_transfer(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Transferencia bancaria');

        $this->assertSame('transfer', $intent->payment_method);
    }

    public function test_detects_urgent(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Lo necesito ya');

        $this->assertSame('urgent', $intent->priority_level);
    }

    public function test_today_becomes_at_least_high_priority(): void
    {
        $order = $this->makeOrder();
        $plan = $this->planner()->parseIntentFromMessage($order, 'Lo ocupo hoy');

        $this->assertSame('high', $plan->priority_level);
        $this->assertSame(config('fulfillment.priority_scores.high', 70), $plan->priority_score);
    }

    public function test_ambiguous_delivery_lowers_confidence(): void
    {
        $intent = $this->parser()->parse($this->makeOrder(), 'Yo paso o me lo llevan');

        $this->assertSame('unknown', $intent->delivery_method);
        $this->assertLessThan(70, $intent->confidence);
        $this->assertNotEmpty($intent->metadata['delivery_method_matches'] ?? []);
    }

    public function test_existing_manual_values_are_preserved(): void
    {
        $order = $this->makeOrder();
        $plan = $order->fulfillmentPlan;

        $plan->forceFill([
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
            'priority_level' => 'urgent',
            'priority_score' => 95,
            'priority_reason' => 'Manual override',
            'planner_notes' => 'Manual note',
            'metadata_json' => [
                'manual_confirmation' => [
                    'delivery_method' => true,
                    'payment_method' => true,
                    'priority_level' => true,
                    'priority_score' => true,
                    'priority_reason' => true,
                    'planner_notes' => true,
                ],
                'keep_me' => true,
            ],
        ])->save();

        $updated = $this->planner()->parseIntentFromMessage($order, 'Me lo llevan hoy por sinpe');

        $this->assertSame('pickup', $updated->delivery_method);
        $this->assertSame('cash', $updated->payment_method);
        $this->assertSame('urgent', $updated->priority_level);
        $this->assertSame(95, $updated->priority_score);
        $this->assertSame('Manual override', $updated->priority_reason);
        $this->assertSame('Manual note', $updated->planner_notes);
        $this->assertTrue($updated->metadata_json['keep_me']);
        $this->assertArrayHasKey('fulfillment_intent', $updated->metadata_json);
    }

    private function parser(): FulfillmentIntentParser
    {
        return app(FulfillmentIntentParser::class);
    }

    private function planner(): FulfillmentPlannerService
    {
        return app(FulfillmentPlannerService::class);
    }

    private function makeOrder(): Order
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
