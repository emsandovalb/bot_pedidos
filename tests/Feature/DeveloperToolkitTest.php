<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperToolkitTest extends TestCase
{
    use RefreshDatabase;

    public function test_toolkit_page_is_only_accessible_in_local_or_debug(): void
    {
        config(['app.debug' => false]);

        $user = $this->makeToolkitUser();

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertNotFound();
    }

    public function test_toolkit_page_renders_the_new_label(): void
    {
        config(['app.debug' => true]);

        $user = $this->makeToolkitUser();

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertOk()
            ->assertSee('Business Scenario Simulator')
            ->assertSee('Business Scenarios')
            ->assertSee('Custom Ingestion Playground');
    }

    public function test_whatsapp_playground_creates_incoming_message_and_order(): void
    {
        [$user, $connection] = $this->prepareToolkitContext();

        $response = $this->actingAs($user)
            ->post(route('developer.webhook-simulator.send'), [
                'provider' => 'whatsapp',
                'payload_source' => 'fields',
                'phone_number_id' => $connection->provider_phone_number_id,
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255510001',
                'message_id' => 'wamid.toolkit-whatsapp-1',
                'message_text' => '2 bolsas de jardin',
            ]);

        $response->assertOk()
            ->assertSee('View latest order');

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $user->organization_id,
            'provider' => 'whatsapp',
            'external_message_id' => 'wamid.toolkit-whatsapp-1',
            'from_identifier' => '50255510001',
            'raw_text' => '2 bolsas de jardin',
        ]);

        $this->assertDatabaseHas('orders', [
            'organization_id' => $user->organization_id,
            'source_channel' => 'whatsapp',
            'external_message_id' => 'wamid.toolkit-whatsapp-1',
        ]);
    }

    public function test_telegram_playground_creates_incoming_message_and_order(): void
    {
        [$user, $connection] = $this->prepareToolkitContext();

        $response = $this->actingAs($user)
            ->post(route('developer.webhook-simulator.send'), [
                'provider' => 'telegram',
                'payload_source' => 'fields',
                'phone_number_id' => $connection->provider_phone_number_id,
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '4001',
                'message_id' => '9010001',
                'message_text' => '2 bolsas de jardin',
            ]);

        $response->assertOk()
            ->assertSee('View latest order');

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $user->organization_id,
            'provider' => 'telegram',
            'external_message_id' => '9010001',
            'from_identifier' => '4001',
            'raw_text' => '2 bolsas de jardin',
        ]);

        $this->assertDatabaseHas('orders', [
            'organization_id' => $user->organization_id,
            'source_channel' => 'telegram',
            'external_message_id' => '9010001',
        ]);
    }

    public function test_can_generate_small_hardware_store_scenario(): void
    {
        [$user] = $this->prepareToolkitContext();

        $response = $this->actingAs($user)
            ->post('/developer/toolkit/scenarios/small-hardware-store');

        $response->assertOk()
            ->assertSee('Escenario Ferreteria pequena generado.')
            ->assertSee('Generar escenario')
            ->assertSee('Reiniciar datos demo');

        $this->assertSame(
            30,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('external_message_id', 'like', 'demo-%')
                ->count(),
        );

        $this->assertSame(
            20,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('source_channel', 'whatsapp')
                ->where('external_message_id', 'like', 'demo-whatsapp-%')
                ->count(),
        );

        $this->assertSame(
            10,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('source_channel', 'telegram')
                ->where('external_message_id', 'like', 'demo-telegram-%')
                ->count(),
        );

        foreach ([
            Order::STATUS_PENDING_REVIEW,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY_FOR_DISPATCH,
            Order::STATUS_DISPATCHED,
            Order::STATUS_CANCELLED,
        ] as $status) {
            $this->assertGreaterThan(
                0,
                Order::query()
                    ->where('organization_id', $user->organization_id)
                    ->where('external_message_id', 'like', 'demo-%')
                    ->where('status', $status)
                    ->count(),
                sprintf('Expected at least one demo order in status %s.', $status),
            );
        }

        $this->assertSame(
            30,
            IncomingMessage::query()
                ->where('organization_id', $user->organization_id)
                ->where('external_message_id', 'like', 'demo-%')
                ->count(),
        );

        $this->assertSame(
            15,
            \App\Models\CustomerIdentity::query()
                ->where('organization_id', $user->organization_id)
                ->get()
                ->filter(static fn ($identity): bool => data_get($identity->metadata_json, 'demo_generated') === true)
                ->count(),
        );

        $this->assertSame(
            2,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('external_message_id', 'like', 'demo-%')
                ->whereNotNull('possible_duplicate_of_order_id')
                ->count(),
        );
    }

    public function test_duplicate_generation_creates_duplicate_orders(): void
    {
        [$user] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'qa',
                'qa_case' => 'duplicate',
                'provider' => 'whatsapp',
            ])
            ->assertOk();

        $this->assertGreaterThan(
            0,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->whereNotNull('possible_duplicate_of_order_id')
                ->count(),
        );
    }

    public function test_vip_generation_creates_vip_level_history(): void
    {
        [$user] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'qa',
                'qa_case' => 'vip',
                'provider' => 'whatsapp',
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(
            20,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('notes', 'like', '%variant=vip%')
                ->count(),
        );
    }

    public function test_generated_orders_are_visible_in_operations_center_dataset(): void
    {
        [$user, $connection] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post('/developer/toolkit/scenarios/small-hardware-store')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Benditio Operations Center')
            ->assertSeeText('3 cajas de clavos');
    }

    public function test_reset_removes_only_demo_generated_data_and_preserves_non_demo_orders(): void
    {
        [$user] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post('/developer/toolkit/scenarios/small-hardware-store')
            ->assertOk()
            ->assertSee('Escenario Ferreteria pequena generado.');

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
            ->post('/developer/toolkit/reset-demo-data')
            ->assertOk()
            ->assertSee('Reset completed');

        $this->assertSame(
            0,
            Order::query()
                ->where('organization_id', $user->organization_id)
                ->where('external_message_id', 'like', 'demo-%')
                ->count(),
        );

        $this->assertSame(
            0,
            IncomingMessage::query()
                ->where('organization_id', $user->organization_id)
                ->where('external_message_id', 'like', 'demo-%')
                ->count(),
        );

        $this->assertSame(
            0,
            \App\Models\CustomerIdentity::query()
                ->where('organization_id', $user->organization_id)
                ->get()
                ->filter(static fn ($identity): bool => data_get($identity->metadata_json, 'demo_generated') === true)
                ->count(),
        );

        $this->assertDatabaseHas('orders', [
            'organization_id' => $user->organization_id,
            'external_message_id' => 'real-order-1',
        ]);
    }

    public function test_generated_orders_are_visible_in_operations_center(): void
    {
        [$user, $connection] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.send'), [
                'provider' => 'whatsapp',
                'payload_source' => 'fields',
                'phone_number_id' => $connection->provider_phone_number_id,
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255510001',
                'message_id' => 'wamid.operations-1',
                'message_text' => '2 bolsas de jardin',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('2 bolsas de jardin');
    }

    /**
     * @return array{0: User, 1: ChannelConnection}
     */
    private function prepareToolkitContext(): array
    {
        config(['app.debug' => true]);

        $user = $this->makeToolkitUser();

        $this->actingAs($user)
            ->get(route('developer.webhook-simulator'))
            ->assertOk();

        return [$user, $this->connectionFor($user)];
    }

    private function makeToolkitUser(): User
    {
        $organization = Organization::query()->create([
            'name' => 'Developer Toolkit Org',
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
            'channel_identifier' => 'toolkit-whatsapp-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        return $user->fresh();
    }

    private function connectionFor(User $user): ChannelConnection
    {
        return ChannelConnection::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->firstOrFail();
    }
}
