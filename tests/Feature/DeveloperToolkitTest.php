<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
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
            ->assertSee('Developer Toolkit')
            ->assertSee('Webhook Playground')
            ->assertSee('Scenario Generator');
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

    public function test_scenario_generation_populates_demo_data(): void
    {
        [$user] = $this->prepareToolkitContext();

        $response = $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'scenario',
                'scenario' => 'ferreteria_pequena',
                'provider' => 'whatsapp',
            ]);

        $response->assertOk()
            ->assertSee('Scenario generated');

        $this->assertGreaterThan(0, Order::query()->where('organization_id', $user->organization_id)->where('notes', 'like', '[developer-toolkit]%')->count());
        $this->assertGreaterThan(0, User::query()->count());
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

    public function test_reset_removes_demo_orders_and_customers_without_touching_the_organization(): void
    {
        [$user, $connection] = $this->prepareToolkitContext();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.send'), [
                'provider' => 'whatsapp',
                'payload_source' => 'fields',
                'phone_number_id' => $connection->provider_phone_number_id,
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255510001',
                'message_id' => 'wamid.reset-1',
                'message_text' => '2 bolsas de jardin',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.generate'), [
                'action' => 'customers',
                'customer_count' => 10,
                'provider' => 'whatsapp',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('developer.webhook-simulator.reset'), [
                'scope' => 'environment',
                'confirm' => '1',
            ])
            ->assertOk()
            ->assertSee('Reset completed');

        $this->assertSame(0, Order::query()->where('organization_id', $user->organization_id)->where('notes', 'like', '[developer-toolkit]%')->count());
        $this->assertSame(0, \App\Models\Customer::query()->where('organization_id', $user->organization_id)->where('external_id', 'like', 'developer-toolkit:customer:%')->count());
        $this->assertDatabaseHas('organizations', [
            'id' => $user->organization_id,
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
