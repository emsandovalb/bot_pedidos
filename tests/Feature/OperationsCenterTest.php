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
use Tests\TestCase;

class OperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_operations_center(): void
    {
        [$user] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Benditio Operations Center')
            ->assertSeeText('Smart Inbox');
    }

    public function test_inbox_renders_orders_and_selection_is_preselected_by_query(): void
    {
        [$user, $selectedOrder, $vipOrder] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $selectedOrder->id]))
            ->assertOk()
            ->assertSeeText($selectedOrder->customer->name)
            ->assertSeeText($selectedOrder->raw_message_text)
            ->assertSeeText('Customer context');
    }

    public function test_customer_panel_shows_context_for_selected_order(): void
    {
        [$user, $selectedOrder] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['order' => $selectedOrder->id]))
            ->assertOk()
            ->assertSeeText('Customer context')
            ->assertSeeText('Total orders')
            ->assertSeeText('Favorite products')
            ->assertSeeText('Open notifications')
            ->assertSeeText('Recent activity');
    }

    public function test_filters_scope_the_inbox(): void
    {
        [$user, $selectedOrder, $vipOrder] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index', ['status' => 'preparando']))
            ->assertOk()
            ->assertSeeText('Preparando');

        $this->actingAs($user)
            ->get(route('operations.index', ['channel' => 'telegram']))
            ->assertOk()
            ->assertSeeText($selectedOrder->raw_message_text)
            ->assertDontSeeText($vipOrder->raw_message_text);

        $this->actingAs($user)
            ->get(route('operations.index', ['priority' => 'duplicate']))
            ->assertOk()
            ->assertSeeText('Duplicado');

        $this->actingAs($user)
            ->get(route('operations.index', ['search' => 'telegram especial']))
            ->assertOk()
            ->assertSeeText('telegram especial');
    }

    public function test_organization_isolation_hides_foreign_orders(): void
    {
        [$user, , , $foreignOrder] = $this->makeOperationsFixture();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertDontSeeText($foreignOrder->customer->name)
            ->assertDontSeeText($foreignOrder->raw_message_text);
    }

    /**
     * @return array{0: User, 1: Order, 2: Order, 3: Order}
     */
    private function makeOperationsFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Operations Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Operations Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@operations',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Operations Owner',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => 'password',
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $whatsappProduct = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Bolsa de jardin',
                'sku' => 'JARDIN-01',
                'unit_label' => 'bolsa',
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $telegramProduct = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => 'Telegram especial',
                'sku' => 'TEL-02',
                'unit_label' => 'unidad',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            $vipCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Maria VIP',
                'phone' => '+50255550001',
                'external_id' => null,
            ]);

            $selectedCustomer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Juan Telegram',
                'phone' => '+50255550002',
                'external_id' => null,
            ]);

            $foreignOrganization = Organization::create([
                'name' => 'Foreign Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $foreignBranch = Branch::create([
                'organization_id' => $foreignOrganization->id,
                'name' => 'Foreign Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => '@foreign',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $foreignCustomer = Customer::create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'name' => 'Foreign Customer',
                'phone' => '+50255559999',
                'external_id' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:05:00'));
            $vipOrder = $this->createOrder($vipCustomer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'whatsapp urgent order',
                'parser_confidence' => 0.42,
                'possible_duplicate_of_order_id' => null,
            ], $whatsappProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:10:00'));
            $duplicateOrder = $this->createOrder($vipCustomer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'whatsapp',
                'raw_message_text' => 'whatsapp duplicate order',
                'parser_confidence' => 0.88,
                'possible_duplicate_of_order_id' => $vipOrder->id,
            ], $whatsappProduct);

            Carbon::setTestNow(Carbon::parse('2026-06-30 09:12:00'));
            $duplicateOrder->forceFill(['reviewed_at' => now()])->saveQuietly();

            for ($index = 0; $index < 18; $index++) {
                Carbon::setTestNow(Carbon::parse('2026-06-30 09:' . str_pad((string) (11 + $index), 2, '0', STR_PAD_LEFT) . ':00'));
                $this->createOrder($vipCustomer, $branch, [
                    'status' => $index % 4 === 0
                        ? Order::STATUS_PREPARING
                        : Order::STATUS_PENDING_REVIEW,
                    'source_channel' => $index % 2 === 0 ? 'whatsapp' : 'telegram',
                    'raw_message_text' => 'vip backlog order ' . $index,
                    'parser_confidence' => 0.6,
                    'possible_duplicate_of_order_id' => null,
                ], $index % 2 === 0 ? $whatsappProduct : $telegramProduct);
            }

            Carbon::setTestNow(Carbon::parse('2026-06-30 10:00:00'));
            $selectedOrder = $this->createOrder($selectedCustomer, $branch, [
                'status' => Order::STATUS_PENDING_REVIEW,
                'source_channel' => 'telegram',
                'raw_message_text' => 'telegram especial',
                'parser_confidence' => 0.93,
                'possible_duplicate_of_order_id' => null,
            ], $telegramProduct);

            Order::query()->create([
                'organization_id' => $foreignOrganization->id,
                'branch_id' => $foreignBranch->id,
                'customer_id' => $foreignCustomer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'whatsapp',
                'external_message_id' => 'foreign-msg-1',
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => 0.77,
                'raw_message_text' => 'foreign order',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'foreign-fingerprint',
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
                $selectedOrder->fresh(['customer']),
                $duplicateOrder->fresh(['customer']),
                Order::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->load('customer'),
            ];
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createOrder(Customer $customer, Branch $branch, array $attributes, Product $product): Order
    {
        $order = Order::create([
            'organization_id' => $customer->organization_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'possible_duplicate_of_order_id' => $attributes['possible_duplicate_of_order_id'] ?? null,
            'source_channel' => $attributes['source_channel'],
            'external_message_id' => fake()->uuid(),
            'status' => $attributes['status'],
            'parser_confidence' => $attributes['parser_confidence'],
            'raw_message_text' => $attributes['raw_message_text'],
            'parsed_payload_json' => ['items' => []],
            'duplicate_score' => ($attributes['possible_duplicate_of_order_id'] ?? null) !== null ? 95 : null,
            'duplicate_reason' => ($attributes['possible_duplicate_of_order_id'] ?? null) !== null ? 'Possible duplicate order.' : null,
            'duplicate_checked_at' => now(),
            'order_fingerprint' => fake()->uuid(),
            'notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => ! empty($attributes['reviewed_at']) ? now() : null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => $attributes['status'] === Order::STATUS_PREPARING ? now() : null,
            'ready_for_dispatch_at' => $attributes['status'] === Order::STATUS_READY_FOR_DISPATCH ? now() : null,
            'dispatched_at' => $attributes['status'] === Order::STATUS_DISPATCHED ? now() : null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'pack',
            'raw_text' => $attributes['raw_message_text'],
            'matched_text' => $attributes['raw_message_text'],
            'confidence_score' => $attributes['parser_confidence'],
            'notes' => null,
            'sort_order' => 0,
        ]);

        return $order->fresh(['customer', 'orderItems.product', 'possibleDuplicateOf']);
    }
}
