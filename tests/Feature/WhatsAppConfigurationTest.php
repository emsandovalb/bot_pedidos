<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_save_persists_encrypted_credentials(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->post(route('channels.whatsapp.configuration.save'), [
                'provider_app_id' => '123456789012345',
                'provider_app_secret' => 'app-secret-value',
                'provider_access_token' => 'access-token-value',
                'provider_verify_token' => 'verify-token-value',
                'provider_webhook_secret' => 'webhook-secret-value',
                'provider_phone_number_id' => '555550000000001',
                'provider_business_account_id' => '555550000000002',
                'provider_display_phone' => '+502 5555 0101',
                'provider_api_version' => 'v21.0',
                'provider_business_name' => 'Benditio',
                'provider_business_timezone' => 'America/Guatemala',
                'provider_business_country' => 'GT',
                'action' => 'save',
            ])
            ->assertRedirect(route('channels.whatsapp.configuration'));

        $connection = $this->connectionFor($user);

        $this->assertSame('123456789012345', $connection->provider_app_id);
        $this->assertSame('app-secret-value', $connection->provider_app_secret);
        $this->assertSame('access-token-value', $connection->provider_access_token);
        $this->assertSame('verify-token-value', $connection->provider_verify_token);
        $this->assertSame('webhook-secret-value', $connection->provider_webhook_secret);
        $this->assertSame(ChannelConnection::STATUS_READY_FOR_VERIFICATION, $connection->provider_configuration_status);
        $this->assertSame(ChannelConnection::STATUS_READY_FOR_VERIFICATION, $connection->provider_status);
        $this->assertNotNull($connection->provider_last_validation_at);

        $rawRow = DB::table('channel_connections')->where('id', $connection->id)->first();

        $this->assertNotSame('app-secret-value', $rawRow->provider_app_secret);
        $this->assertNotSame('access-token-value', $rawRow->provider_access_token);
        $this->assertNotSame('verify-token-value', $rawRow->provider_verify_token);
    }

    public function test_configuration_page_masks_secrets(): void
    {
        $user = $this->createOrganizationUser();
        $service = app(WhatsAppConfigurationService::class);

        $service->saveConfiguration($user->organization_id, [
            'provider_app_id' => '123456789012345',
            'provider_app_secret' => 'app-secret-value',
            'provider_access_token' => 'access-token-value',
            'provider_verify_token' => 'verify-token-value',
            'provider_webhook_secret' => 'webhook-secret-value',
            'provider_phone_number_id' => '555550000000001',
            'provider_business_account_id' => '555550000000002',
            'provider_display_phone' => '+502 5555 0101',
            'provider_api_version' => 'v21.0',
            'provider_business_name' => 'Benditio',
            'provider_business_timezone' => 'America/Guatemala',
            'provider_business_country' => 'GT',
        ]);

        $connection = $this->connectionFor($user);
        $masked = $service->maskSensitiveData($connection);

        $response = $this->actingAs($user)->get(route('channels.whatsapp.configuration'));

        $response->assertOk()
            ->assertSeeText($masked['provider_app_secret'])
            ->assertSeeText($masked['provider_access_token'])
            ->assertSeeText($masked['provider_verify_token'])
            ->assertSeeText($masked['provider_webhook_secret'])
            ->assertDontSeeText('app-secret-value')
            ->assertDontSeeText('access-token-value')
            ->assertDontSeeText('verify-token-value')
            ->assertDontSeeText('webhook-secret-value');
    }

    public function test_partial_configuration_maps_to_missing_credentials_health(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->post(route('channels.whatsapp.configuration.save'), [
                'provider_app_id' => '123456789012345',
                'provider_app_secret' => 'app-secret-value',
                'provider_api_version' => 'v21.0',
                'action' => 'validate',
            ])
            ->assertRedirect(route('channels.whatsapp.configuration'));

        $connection = $this->connectionFor($user);

        $this->assertSame(ChannelConnection::STATUS_MISSING_CREDENTIALS, $connection->provider_configuration_status);
        $this->assertSame(ChannelConnection::STATUS_WARNING, $connection->provider_status);
        $this->assertStringContainsString('Missing credentials', (string) $connection->provider_last_validation_error);

        $health = app(ProviderLifecycleManager::class)->validate('whatsapp', $user->organization_id);

        $this->assertInstanceOf(ProviderHealth::class, $health);
        $this->assertSame('warning', $health->status);
        $this->assertSame('missing', $health->credentials_status);
        $this->assertSame('missing_credentials', $health->metadata['configuration_status'] ?? null);
    }

    public function test_complete_configuration_maps_to_ready_health(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->post(route('channels.whatsapp.configuration.save'), [
                'provider_app_id' => '123456789012345',
                'provider_app_secret' => 'app-secret-value',
                'provider_access_token' => 'access-token-value',
                'provider_verify_token' => 'verify-token-value',
                'provider_webhook_secret' => 'webhook-secret-value',
                'provider_phone_number_id' => '555550000000001',
                'provider_business_account_id' => '555550000000002',
                'provider_display_phone' => '+502 5555 0101',
                'provider_api_version' => 'v21.0',
                'provider_business_name' => 'Benditio',
                'provider_business_timezone' => 'America/Guatemala',
                'provider_business_country' => 'GT',
                'action' => 'save',
            ])
            ->assertRedirect(route('channels.whatsapp.configuration'));

        $health = app(ProviderLifecycleManager::class)->validate('whatsapp', $user->organization_id);
        $connection = $this->connectionFor($user);

        $this->assertInstanceOf(ProviderHealth::class, $health);
        $this->assertSame('ready', $health->status);
        $this->assertTrue($health->healthy);
        $this->assertSame('configured', $health->credentials_status);
        $this->assertSame('pending', $health->webhook_status);
        $this->assertSame(ChannelConnection::STATUS_READY_FOR_VERIFICATION, $connection->provider_configuration_status);
        $this->assertSame(ChannelConnection::STATUS_READY_FOR_VERIFICATION, $connection->provider_status);
        $this->assertNotNull($connection->provider_last_validation_at);
    }

    public function test_configuration_updates_are_isolated_per_organization(): void
    {
        $service = app(WhatsAppConfigurationService::class);

        $organizationA = Organization::query()->create([
            'name' => 'Org A',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Org B',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $service->saveConfiguration($organizationA->id, [
            'provider_app_id' => '123456789012345',
            'provider_app_secret' => 'app-secret-value',
            'provider_access_token' => 'access-token-value',
            'provider_verify_token' => 'verify-token-value',
            'provider_webhook_secret' => 'webhook-secret-value',
            'provider_phone_number_id' => '555550000000001',
            'provider_business_account_id' => '555550000000002',
            'provider_api_version' => 'v21.0',
        ]);

        $service->loadConfiguration($organizationB->id);

        app(ProviderLifecycleManager::class)->validate('whatsapp', $organizationA->id);

        $connectionA = ChannelConnection::query()
            ->where('organization_id', $organizationA->id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->firstOrFail();

        $connectionB = ChannelConnection::query()
            ->where('organization_id', $organizationB->id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->firstOrFail();

        $this->assertNotNull($connectionA->provider_last_validation_at);
        $this->assertNull($connectionB->provider_last_validation_at);
        $this->assertSame(ChannelConnection::STATUS_DRAFT, $connectionB->provider_configuration_status);
    }

    private function createOrganizationUser(): User
    {
        $organization = Organization::query()->create([
            'name' => 'Benditio',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        return User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }

    private function connectionFor(User $user): ChannelConnection
    {
        return ChannelConnection::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->firstOrFail();
    }
}
