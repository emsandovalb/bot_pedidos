<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DailyOrderClosure;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyOrderClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_closures_index(): void
    {
        [$user] = $this->makeOwnerWithBranches();

        $this->actingAs($user)
            ->get(route('daily-order-closures.index'))
            ->assertOk()
            ->assertSee('Cierres diarios');
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        [$user, , $branchOne] = $this->makeOwnerWithBranches();

        $this->actingAs($user)
            ->get(route('daily-order-closures.create', ['branch_id' => $branchOne->id]))
            ->assertOk()
            ->assertSee('Nuevo cierre')
            ->assertSee($branchOne->name);
    }

    public function test_creating_closure_stores_correct_counts_by_status(): void
    {
        [$user, $branchOne, $branchTwo, $date] = $this->seedClosureScenario();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), [
                'branch_id' => $branchOne->id,
                'closure_date' => $date->toDateString(),
                'notes' => 'End of day snapshot.',
            ])
            ->assertRedirect();

        $closure = DailyOrderClosure::query()->firstOrFail();

        $this->assertSame(7, $closure->total_orders);
        $this->assertSame(1, $closure->pending_review_count);
        $this->assertSame(1, $closure->confirmed_count);
        $this->assertSame(1, $closure->preparing_count);
        $this->assertSame(1, $closure->ready_for_dispatch_count);
        $this->assertSame(1, $closure->dispatched_count);
        $this->assertSame(1, $closure->cancelled_count);
        $this->assertSame(1, $closure->rejected_count);
        $this->assertSame('End of day snapshot.', $closure->notes);
        $this->assertSame($user->id, $closure->closed_by);
        $this->assertSame($branchOne->id, $closure->branch_id);
        $this->assertSame($branchOne->organization_id, $closure->organization_id);
    }

    public function test_creating_closure_sums_total_items(): void
    {
        [$user, $branchOne, , $date] = $this->seedClosureScenario();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), [
                'branch_id' => $branchOne->id,
                'closure_date' => $date->toDateString(),
            ])
            ->assertRedirect();

        $closure = DailyOrderClosure::query()->firstOrFail();

        $this->assertSame('10.00', (string) $closure->total_items);
    }

    public function test_duplicate_closure_for_same_branch_and_date_is_rejected(): void
    {
        [$user, $branchOne, , $date] = $this->seedClosureScenario();

        $payload = [
            'branch_id' => $branchOne->id,
            'closure_date' => $date->toDateString(),
        ];

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), $payload)
            ->assertSessionHasErrors('closure_date');

        $this->assertSame(1, DailyOrderClosure::query()->count());
    }

    public function test_show_page_displays_products_items_summary(): void
    {
        [$user, $branchOne, , $date, $product] = $this->seedClosureScenario();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), [
                'branch_id' => $branchOne->id,
                'closure_date' => $date->toDateString(),
            ])
            ->assertRedirect();

        $closure = DailyOrderClosure::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('daily-order-closures.show', $closure))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('3 cajas de vasos')
            ->assertSee('Pedidos vinculados');
    }

    public function test_export_returns_csv_with_expected_headers_and_content(): void
    {
        [$user, $branchOne, , $date, $product] = $this->seedClosureScenario();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), [
                'branch_id' => $branchOne->id,
                'closure_date' => $date->toDateString(),
            ])
            ->assertRedirect();

        $closure = DailyOrderClosure::query()->firstOrFail();

        $response = $this->actingAs($user)->get(route('daily-order-closures.export', $closure));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('closure_date,branch,order_id,order_status,customer,raw_message_text,item_quantity,item_unit,product_name,item_raw_text,order_notes,created_at', $csv);
        $this->assertStringContainsString($branchOne->name, $csv);
        $this->assertStringContainsString($product->name, $csv);
        $this->assertStringContainsString('3 cajas de vasos', $csv);
        $this->assertStringContainsString('Packed early', $csv);
    }

    public function test_closure_only_counts_orders_for_selected_branch_and_date(): void
    {
        [$user, $branchOne, $branchTwo, $date] = $this->seedClosureScenario();

        $this->actingAs($user)
            ->post(route('daily-order-closures.store'), [
                'branch_id' => $branchOne->id,
                'closure_date' => $date->toDateString(),
            ])
            ->assertRedirect();

        $closure = DailyOrderClosure::query()->firstOrFail();

        $this->assertSame(7, $closure->total_orders);
        $this->assertSame(10.00, (float) $closure->total_items);
        $this->assertDatabaseMissing('daily_order_closures', [
            'branch_id' => $branchTwo->id,
        ]);
    }

    public function test_old_legacy_closure_flow_is_not_used(): void
    {
        [$user] = $this->makeOwnerWithBranches();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('daily-order-closures.index'))
            ->assertDontSee(route('closures.index'));

        $this->actingAs($user)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee(route('daily-order-closures.index'))
            ->assertDontSee(route('closures.index'));
    }

    /**
     * @return array{0: User, 1: Branch, 2: Branch, 3: Carbon, 4: Product}
     */
    private function seedClosureScenario(): array
    {
        [$user, $organization, $branchOne, $branchTwo] = $this->makeOwnerWithBranches();
        $date = today();

        $product = Product::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Bolsas de jardin',
            'sku' => 'SKU-100',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $customerOne = $this->makeCustomer($organization, $branchOne, 'Customer One', '5550001');
        $customerTwo = $this->makeCustomer($organization, $branchOne, 'Customer Two', '5550002');
        $customerThree = $this->makeCustomer($organization, $branchOne, 'Customer Three', '5550003');
        $customerFour = $this->makeCustomer($organization, $branchOne, 'Customer Four', '5550004');
        $customerFive = $this->makeCustomer($organization, $branchOne, 'Customer Five', '5550005');
        $customerSix = $this->makeCustomer($organization, $branchOne, 'Customer Six', '5550006');
        $customerSeven = $this->makeCustomer($organization, $branchOne, 'Customer Seven', '5550007');
        $customerBranchTwo = $this->makeCustomer($organization, $branchTwo, 'Other Branch Customer', '5550099');

        $this->makeOrder($organization, $branchOne, $customerOne, Order::STATUS_PENDING_REVIEW, $date, '2 bolsas de jardin', 2, $product, 'Packed early');
        $this->makeOrder($organization, $branchOne, $customerTwo, Order::STATUS_CONFIRMED, $date, '3 cajas de vasos', 3, null);
        $this->makeOrder($organization, $branchOne, $customerThree, Order::STATUS_PREPARING, $date, '1 caja de tomates', 1, null);
        $this->makeOrder($organization, $branchOne, $customerFour, Order::STATUS_READY_FOR_DISPATCH, $date, '1 canasta de frutas', 1, null);
        $this->makeOrder($organization, $branchOne, $customerFive, Order::STATUS_DISPATCHED, $date, '1 paquete de servilletas', 1, null);
        $this->makeOrder($organization, $branchOne, $customerSix, Order::STATUS_CANCELLED, $date, '1 botella de aceite', 1, null);
        $this->makeOrder($organization, $branchOne, $customerSeven, Order::STATUS_REJECTED, $date, '1 kilo de arroz', 1, null);

        $this->makeOrder($organization, $branchTwo, $customerBranchTwo, Order::STATUS_CONFIRMED, $date, '99 cajas de otro local', 99, null);
        $this->makeOrder($organization, $branchOne, $customerOne, Order::STATUS_CONFIRMED, $date->copy()->addDay(), '88 cajas del siguiente dia', 88, null);

        return [$user->fresh(), $branchOne->fresh(), $branchTwo->fresh(), $date, $product->fresh()];
    }

    /**
     * @return array{0: User, 1: Organization, 2: Branch, 3: Branch}
     */
    private function makeOwnerWithBranches(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Closure Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branchOne = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Branch One',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@branch-one',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $branchTwo = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Branch Two',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@branch-two',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Closure Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        return [$user->fresh(), $organization->fresh(), $branchOne->fresh(), $branchTwo->fresh()];
    }

    private function makeCustomer(Organization $organization, Branch $branch, string $name, string $phone): Customer
    {
        return Customer::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => $phone,
            'external_id' => null,
        ]);
    }

    private function makeOrder(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $status,
        Carbon $date,
        string $rawText,
        int $quantity,
        ?Product $product,
        ?string $notes = null
    ): Order {
        $order = Order::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => 'telegram',
            'external_message_id' => null,
            'status' => $status,
            'parser_confidence' => 0.99,
            'raw_message_text' => $rawText,
            'parsed_payload_json' => ['items' => []],
            'notes' => $notes,
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

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'created_at' => $date->copy()->setTime(10, 0, 0),
                'updated_at' => $date->copy()->setTime(10, 0, 0),
            ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'quantity' => $quantity,
            'unit' => $product?->unit_label ?? 'unidad',
            'raw_text' => $rawText,
            'matched_text' => $product?->name ?? $rawText,
            'confidence_score' => 0.95,
            'notes' => null,
            'sort_order' => 0,
        ]);

        return $order->fresh()->load('orderItems.product');
    }
}
