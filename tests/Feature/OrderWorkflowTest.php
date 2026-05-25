<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_order_can_move_to_preparing(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_PREPARING, $order->status);
        $this->assertNotNull($order->preparing_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_PREPARING,
            'changed_via' => 'admin_ui',
        ]);
    }

    public function test_preparing_order_can_move_to_ready_for_dispatch(): void
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_READY_FOR_DISPATCH, $order->status);
        $this->assertNotNull($order->ready_for_dispatch_at);
    }

    public function test_ready_for_dispatch_order_can_move_to_dispatched(): void
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertNotNull($order->dispatched_at);
    }

    public function test_preparing_order_can_be_cancelled(): void
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_ready_for_dispatch_order_can_be_cancelled(): void
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_dispatched_order_cannot_be_cancelled(): void
    {
        [$user, $order] = $this->makeDispatchedOrder();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertStatus(422);

        $order->refresh();

        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertNull($order->cancelled_at);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        [$user, $order] = $this->makePendingReviewOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertStatus(422);

        $order->refresh();

        $this->assertSame(Order::STATUS_PENDING_REVIEW, $order->status);
    }

    public function test_every_valid_transition_creates_order_status_history(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_PREPARING,
        ]);
    }

    public function test_timestamps_are_set_correctly(): void
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->preparing_at);
        $this->assertNull($order->ready_for_dispatch_at);
        $this->assertNull($order->dispatched_at);

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->preparing_at);
        $this->assertNotNull($order->ready_for_dispatch_at);
        $this->assertNull($order->dispatched_at);

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertNotNull($order->dispatched_at);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makePendingReviewOrder(): array
    {
        return $this->makeOrder();
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeConfirmedOrder(): array
    {
        [$user, $order] = $this->makeOrder();

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makePreparingOrder(): array
    {
        [$user, $order] = $this->makeConfirmedOrder();

        $this->actingAs($user)
            ->post(route('orders.prepare', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeReadyForDispatchOrder(): array
    {
        [$user, $order] = $this->makePreparingOrder();

        $this->actingAs($user)
            ->post(route('orders.ready-for-dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeDispatchedOrder(): array
    {
        [$user, $order] = $this->makeReadyForDispatchOrder();

        $this->actingAs($user)
            ->post(route('orders.dispatch', $order))
            ->assertRedirect(route('orders.show', $order));

        return [$user, $order->refresh()];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeOrder(): array
    {
        $organization = Organization::create([
            'name' => 'Workflow Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Workflow Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@workflow',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Workflow Customer',
            'phone' => '555-0202',
            'external_id' => null,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Workflow Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardin',
        );

        return [$user, $order->refresh()];
    }
}
