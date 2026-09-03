<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 #6 — roles/permisos sobre pedidos. Antes cualquier usuario autenticado
 * (incluido el rol `viewer`, de solo lectura) podía confirmar, rechazar,
 * editar o avanzar pedidos. Ahora lo gatea OrderPolicy.
 */
class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_confirm_reject_update_or_advance_orders(): void
    {
        ['viewer' => $viewer, 'order' => $order] = $this->context();

        $this->actingAs($viewer)->post(route('orders.confirm', $order))->assertForbidden();
        $this->actingAs($viewer)->post(route('orders.reject', $order))->assertForbidden();
        $this->actingAs($viewer)->post(route('orders.cancel', $order))->assertForbidden();
        $this->actingAs($viewer)->get(route('orders.edit', $order))->assertForbidden();
        $this->actingAs($viewer)->patch(route('orders.update', $order), [
            'items_json' => json_encode([]),
        ])->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING_REVIEW, $order->fresh()->status);
    }

    public function test_viewer_can_still_read_orders(): void
    {
        ['viewer' => $viewer, 'order' => $order] = $this->context();

        $this->actingAs($viewer)->get(route('orders.index'))->assertOk();
        $this->actingAs($viewer)->get(route('orders.show', $order))->assertOk();
    }

    public function test_seller_cannot_act_on_orders_from_another_branch(): void
    {
        ['seller_other_branch' => $seller, 'order' => $order] = $this->context();

        // 404: el pedido no es visible para el vendedor de otra sucursal.
        $this->actingAs($seller)->post(route('orders.confirm', $order))->assertNotFound();
    }

    public function test_seller_can_confirm_orders_from_their_branch(): void
    {
        ['seller_same_branch' => $seller, 'order' => $order] = $this->context();

        $this->actingAs($seller)->post(route('orders.confirm', $order))->assertRedirect();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_owner_can_confirm_orders(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->context();

        $this->actingAs($owner)->post(route('orders.confirm', $order))->assertRedirect();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /**
     * @return array{owner:User, viewer:User, seller_same_branch:User, seller_other_branch:User, order:Order}
     */
    private function context(): array
    {
        $organization = Organization::create([
            'name' => 'Order Auth Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@order-auth-main',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Other Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@order-auth-other',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $mkUser = fn (string $role, ?int $branchId) => User::create([
            'organization_id' => $organization->id,
            'branch_id' => $branchId,
            'role' => $role,
            'name' => ucfirst($role),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $owner = $mkUser(User::ROLE_OWNER, null);
        $organization->update(['owner_user_id' => $owner->id]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Auth Customer',
            'phone' => '555-0199',
            'external_id' => null,
        ]);

        $product = Product::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Bolsas de jardin',
            'sku' => 'JARDIN-AUTH',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        ProductAlias::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'alias' => 'bolsas de jardin',
            'match_weight' => 100,
            'is_active' => true,
        ]);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: '2 bolsas de jardin',
            sourceChannel: 'telegram',
            externalMessageId: 'order-auth-1',
        );

        return [
            'owner' => $owner,
            'viewer' => $mkUser(User::ROLE_VIEWER, null),
            'seller_same_branch' => $mkUser(User::ROLE_SELLER, $branch->id),
            'seller_other_branch' => $mkUser(User::ROLE_SELLER, $otherBranch->id),
            'order' => $order,
        ];
    }
}
