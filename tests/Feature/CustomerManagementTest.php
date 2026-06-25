<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_customers_index(): void
    {
        [$user] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSeeText('Clientes')
            ->assertSeeText('Unifica clientes y canales para entender quién compra en tu negocio.');
    }

    public function test_customers_index_shows_customer_with_order_count(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSeeText($customer->name)
            ->assertSeeText('Total de pedidos')
            ->assertSeeText((string) $customer->orders_count);
    }

    public function test_customers_index_shows_identity_count(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSeeText('Identidades')
            ->assertSeeText((string) $customer->customer_identities_count);
    }

    public function test_authenticated_user_can_view_customer_detail(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Cliente')
            ->assertSeeText('Vista unificada de pedidos, canales e identidades.');
    }

    public function test_customer_detail_shows_identities(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Identidades del cliente')
            ->assertSeeText('Telegram')
            ->assertSeeText('WhatsApp')
            ->assertSeeText('Principal');
    }

    public function test_customer_detail_shows_recent_orders(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Pedidos recientes')
            ->assertSeeText('Pedido #')
            ->assertSeeText('Posible duplicado')
            ->assertSeeText('Bolsas de jardin')
            ->assertSeeText('Pedido original');
    }

    public function test_customer_detail_shows_order_status_counts(): void
    {
        [$user, $customer] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Estados de pedidos')
            ->assertSeeText('Pendiente de revisión')
            ->assertSeeText('Confirmado')
            ->assertSeeText('En preparación')
            ->assertSeeText('Listo para despacho')
            ->assertSeeText('Despachado')
            ->assertSeeText('Cancelado')
            ->assertSeeText('Rechazado');
    }

    public function test_organization_scoping_prevents_seeing_another_organizations_customer(): void
    {
        [$user] = $this->makeScopedData();
        $otherOrganization = Organization::create([
            'name' => 'Other Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);
        $otherBranch = Branch::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@other',
            'status' => Branch::STATUS_ACTIVE,
        ]);
        $otherCustomer = Customer::create([
            'organization_id' => $otherOrganization->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Foreign Customer',
            'phone' => '+50255559999',
            'external_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $otherCustomer))
            ->assertNotFound();
    }

    public function test_navigation_includes_clientes_link(): void
    {
        [$user] = $this->makeScopedData();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Clientes');
    }

    /**
     * @return array{0: User, 1: Customer}
     */
    private function makeScopedData(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 09:00:00'));

        try {
            $organization = Organization::create([
                'name' => 'Customer Org',
                'status' => Organization::STATUS_ACTIVE,
            ]);

            $branch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Main Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@customer-org',
                'status' => Branch::STATUS_ACTIVE,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Owner',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            $organization->update(['owner_user_id' => $user->id]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Maria Garcia',
                'phone' => '+50255550001',
                'external_id' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00'));
            CustomerIdentity::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'provider' => 'telegram',
                'external_user_id' => 'telegram-user-1',
                'external_chat_id' => 'telegram-chat-1',
                'provider_username' => '@maria',
                'phone' => '+50255550001',
                'normalized_phone' => '+50255550001',
                'email' => null,
                'display_name' => 'Maria Garcia',
                'confidence_score' => 100,
                'is_primary' => true,
                'metadata_json' => null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 11:00:00'));
            CustomerIdentity::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'provider' => 'whatsapp',
                'external_user_id' => 'wa-user-1',
                'external_chat_id' => 'wa-chat-1',
                'provider_username' => null,
                'phone' => '+50255550001',
                'normalized_phone' => '+50255550001',
                'email' => null,
                'display_name' => 'Maria WA',
                'confidence_score' => 90,
                'is_primary' => false,
                'metadata_json' => ['match_type' => 'ambiguous_phone'],
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00'));
            $originalOrder = Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => 'msg-1',
                'status' => Order::STATUS_CONFIRMED,
                'parser_confidence' => 0.95,
                'raw_message_text' => 'Pedido original',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-1',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'preparing_at' => null,
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 12:15:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => $originalOrder->id,
                'source_channel' => 'whatsapp',
                'external_message_id' => 'msg-2',
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => 0.88,
                'raw_message_text' => 'Bolsas de jardin',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => 95,
                'duplicate_reason' => 'Same customer and items within duplicate window.',
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-2',
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

            Carbon::setTestNow(Carbon::parse('2026-06-24 13:00:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => 'msg-3',
                'status' => Order::STATUS_PREPARING,
                'parser_confidence' => 0.91,
                'raw_message_text' => 'Pedido en preparación',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-3',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => now(),
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 14:00:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'whatsapp',
                'external_message_id' => 'msg-4',
                'status' => Order::STATUS_READY_FOR_DISPATCH,
                'parser_confidence' => 0.89,
                'raw_message_text' => 'Pedido listo para despacho',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-4',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => null,
                'ready_for_dispatch_at' => now(),
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 15:00:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => 'msg-5',
                'status' => Order::STATUS_DISPATCHED,
                'parser_confidence' => 0.92,
                'raw_message_text' => 'Pedido despachado',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-5',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => null,
                'ready_for_dispatch_at' => null,
                'dispatched_at' => now(),
                'cancelled_at' => null,
                'rejected_at' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 16:00:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'whatsapp',
                'external_message_id' => 'msg-6',
                'status' => Order::STATUS_CANCELLED,
                'parser_confidence' => 0.80,
                'raw_message_text' => 'Pedido cancelado',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-6',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => null,
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => now(),
                'rejected_at' => null,
            ]);

            Carbon::setTestNow(Carbon::parse('2026-06-24 17:00:00'));
            Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => null,
                'possible_duplicate_of_order_id' => null,
                'source_channel' => 'telegram',
                'external_message_id' => 'msg-7',
                'status' => Order::STATUS_REJECTED,
                'parser_confidence' => 0.70,
                'raw_message_text' => 'Pedido rechazado',
                'parsed_payload_json' => ['items' => []],
                'duplicate_score' => null,
                'duplicate_reason' => null,
                'duplicate_checked_at' => now(),
                'order_fingerprint' => 'fingerprint-7',
                'notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => null,
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => now(),
            ]);

            return [$user, $customer->fresh()->loadCount(['orders', 'customerIdentities'])];
        } finally {
            Carbon::setTestNow();
        }
    }
}
