<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedidos filters by status, channel and a search over the customer's name or phone or the order number,
 * and the filters combine.
 */
class OrderListFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_filter_shows_only_that_channel(): void
    {
        ['owner' => $owner, 'orders' => $orders] = $this->context();

        $this->actingAs($owner)->get(route('orders.index', ['channel' => 'whatsapp']))
            ->assertOk()
            ->assertSee('Ana Whatsapp')
            ->assertDontSee('Beto Telegram')
            ->assertDontSee('Carla Telegram')
            ->assertSee('1 pedido');
    }

    public function test_search_finds_a_customer_by_name_phone_or_order_number(): void
    {
        ['owner' => $owner, 'orders' => $orders] = $this->context();

        $this->actingAs($owner)->get(route('orders.index', ['q' => 'beto']))
            ->assertOk()->assertSee('Beto Telegram')->assertDontSee('Ana Whatsapp')->assertDontSee('Carla Telegram');

        $this->actingAs($owner)->get(route('orders.index', ['q' => '8800-3333']))
            ->assertOk()->assertSee('Carla Telegram')->assertDontSee('Beto Telegram');

        $this->actingAs($owner)->get(route('orders.index', ['q' => '#'.$orders['ana']->id]))
            ->assertOk()->assertSee('Ana Whatsapp')->assertDontSee('Beto Telegram');
    }

    public function test_a_name_search_does_not_match_every_phone(): void
    {
        ['owner' => $owner] = $this->context();

        $this->actingAs($owner)->get(route('orders.index', ['q' => 'Nadie Asi']))
            ->assertOk()
            ->assertDontSee('Ana Whatsapp')
            ->assertDontSee('Beto Telegram')
            ->assertSee('0 pedidos');
    }

    public function test_filters_combine_and_status_tabs_keep_them(): void
    {
        ['owner' => $owner] = $this->context();

        $this->actingAs($owner)->get(route('orders.index', ['channel' => 'telegram', 'status' => Order::STATUS_CONFIRMED]))
            ->assertOk()
            ->assertSee('Carla Telegram')
            ->assertDontSee('Beto Telegram')
            ->assertSee('1 pedido')
            // The "En revisión" tab keeps the chosen channel.
            ->assertSee(route('orders.index', ['status' => Order::STATUS_PENDING_REVIEW, 'channel' => 'telegram']));
    }

    public function test_an_unknown_channel_is_refused(): void
    {
        ['owner' => $owner] = $this->context();

        $this->actingAs($owner)->get(route('orders.index', ['channel' => 'fax']))->assertSessionHasErrors('channel');
    }

    /**
     * @return array{owner: User, orders: array<string, Order>}
     */
    private function context(): array
    {
        $organization = Organization::create(['name' => 'Filtros Org', 'status' => Organization::STATUS_ACTIVE]);
        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Sucursal Filtros',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@filtros',
            'status' => Branch::STATUS_ACTIVE,
        ]);
        $owner = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner Filtros',
            'email' => 'owner-filtros@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $order = function (string $name, string $phone, string $channel, string $status) use ($organization, $branch): Order {
            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'phone' => $phone,
            ]);

            return Order::factory()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'source_channel' => $channel,
                'status' => $status,
                'raw_message_text' => 'Pedido de '.$name,
            ]);
        };

        return [
            'owner' => $owner,
            'orders' => [
                'ana' => $order('Ana Whatsapp', '+506 8800-1111', 'whatsapp', Order::STATUS_PENDING_REVIEW),
                'beto' => $order('Beto Telegram', '+506 8800-2222', 'telegram', Order::STATUS_PENDING_REVIEW),
                'carla' => $order('Carla Telegram', '88003333', 'telegram', Order::STATUS_CONFIRMED),
            ],
        ];
    }
}
