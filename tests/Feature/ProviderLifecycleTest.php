<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\Messaging\Manager\MessagingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_capabilities_are_exposed(): void
    {
        $manager = new MessagingManager();

        $capabilities = $manager->capabilities('telegram');

        $this->assertInstanceOf(ProviderCapabilities::class, $capabilities);
        $this->assertTrue($capabilities->receive_messages);
        $this->assertTrue($capabilities->send_messages);
        $this->assertTrue($capabilities->buttons);
        $this->assertTrue($capabilities->reactions);
        $this->assertTrue($capabilities->images);
        $this->assertTrue($capabilities->files);
        $this->assertTrue($capabilities->interactive_buttons);
        $this->assertFalse($capabilities->templates);
        $this->assertFalse($capabilities->catalog);
    }

    public function test_whatsapp_capabilities_are_exposed(): void
    {
        $manager = new MessagingManager();

        $capabilities = $manager->capabilities('whatsapp');

        $this->assertInstanceOf(ProviderCapabilities::class, $capabilities);
        $this->assertTrue($capabilities->templates);
        $this->assertTrue($capabilities->catalog);
        $this->assertTrue($capabilities->buttons);
        $this->assertTrue($capabilities->interactive_buttons);
    }

    public function test_instagram_capabilities_are_placeholder(): void
    {
        $manager = new MessagingManager();

        $capabilities = $manager->capabilities('instagram');

        $this->assertInstanceOf(ProviderCapabilities::class, $capabilities);
        $this->assertFalse($capabilities->receive_messages);
        $this->assertFalse($capabilities->send_messages);
        $this->assertFalse($capabilities->buttons);
        $this->assertFalse($capabilities->reactions);
    }

    public function test_health_dto_is_returned_for_supported_providers(): void
    {
        config()->set('services.telegram.bot_token', 'telegram-test-token');

        $manager = new MessagingManager();

        $health = $manager->health('telegram');

        $this->assertInstanceOf(ProviderHealth::class, $health);
        $this->assertSame('telegram', $health->provider);
        $this->assertSame('healthy', $health->status);
        $this->assertTrue($health->healthy);
        $this->assertTrue($health->connected);
        $this->assertSame('configured', $health->token_status);
        $this->assertSame('configured', $health->credentials_status);
        $this->assertSame('verified', $health->webhook_status);
        $this->assertNotEmpty($health->capabilities);
        $this->assertSame('telegram-bot-api', $health->metadata['transport'] ?? null);
    }

    public function test_validation_dto_reflects_configuration_state(): void
    {
        config()->set('services.telegram.bot_token', 'telegram-test-token');

        $manager = new MessagingManager();

        $validation = $manager->validate('telegram');

        $this->assertInstanceOf(ProviderValidationResult::class, $validation);
        $this->assertTrue($validation->valid);
        $this->assertSame([], $validation->errors);
        $this->assertNotEmpty($validation->warnings);
        $this->assertNotNull($validation->configuration_checked_at);
    }

    public function test_provider_lifecycle_manager_refreshes_channel_health(): void
    {
        config()->set('services.telegram.bot_token', 'telegram-test-token');

        $organization = Organization::query()->create([
            'name' => 'Lifecycle Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        ChannelConnection::query()->create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_TELEGRAM,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => 'telegram',
            'version' => 'v0',
            'provider_version' => 'v0',
            'metadata_json' => ['seed' => true],
        ]);

        $manager = new ProviderLifecycleManager();
        $health = $manager->refreshHealth('telegram', $organization->id);

        $this->assertSame($organization->id, $health->organization_id);
        $this->assertSame('healthy', $health->status);

        $connection = ChannelConnection::query()
            ->where('organization_id', $organization->id)
            ->where('channel', ChannelConnection::CHANNEL_TELEGRAM)
            ->firstOrFail();

        $this->assertSame('healthy', $connection->health_status);
        $this->assertNotNull($connection->last_health_check_at);
        $this->assertSame('configured', $connection->credentials_status);
    }

    public function test_provider_lifecycle_manager_disconnects_channel(): void
    {
        config()->set('services.telegram.bot_token', 'telegram-test-token');

        $organization = Organization::query()->create([
            'name' => 'Lifecycle Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        ChannelConnection::query()->create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_TELEGRAM,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => 'telegram',
            'version' => 'v1',
            'provider_version' => 'v1',
        ]);

        $manager = new ProviderLifecycleManager();
        $health = $manager->disconnect('telegram', $organization->id);

        $this->assertSame('disconnected', $health->status);
        $this->assertFalse($health->connected);

        $connection = ChannelConnection::query()
            ->where('organization_id', $organization->id)
            ->where('channel', ChannelConnection::CHANNEL_TELEGRAM)
            ->firstOrFail();

        $this->assertSame('disconnected', $connection->health_status);
        $this->assertFalse($connection->status === ChannelConnection::STATUS_CONNECTED);
    }

    public function test_provider_lifecycle_manager_keeps_organizations_isolated(): void
    {
        config()->set('services.telegram.bot_token', 'telegram-test-token');

        $organizationA = Organization::query()->create([
            'name' => 'Org A',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Org B',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $connectionA = ChannelConnection::query()->create([
            'organization_id' => $organizationA->id,
            'channel' => ChannelConnection::CHANNEL_TELEGRAM,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => 'telegram',
            'version' => 'v1',
            'provider_version' => 'v1',
        ]);

        $connectionB = ChannelConnection::query()->create([
            'organization_id' => $organizationB->id,
            'channel' => ChannelConnection::CHANNEL_TELEGRAM,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => 'telegram',
            'version' => 'v1',
            'provider_version' => 'v1',
        ]);

        $manager = new ProviderLifecycleManager();
        $manager->refreshHealth('telegram', $organizationA->id);

        $this->assertNotNull($connectionA->fresh()->last_health_check_at);
        $this->assertNull($connectionB->fresh()->last_health_check_at);
    }

    public function test_capabilities_dto_supports_new_and_legacy_keys(): void
    {
        $capabilities = new ProviderCapabilities(
            provider: 'telegram',
            receive_messages: true,
            send_messages: true,
            images: true,
            files: true,
            buttons: true,
            reactions: true,
            send_images: true,
            send_documents: true,
            interactive_buttons: true,
            reaction_support: true,
        );

        $asArray = $capabilities->toArray();

        $this->assertTrue($asArray['images']);
        $this->assertTrue($asArray['files']);
        $this->assertTrue($asArray['buttons']);
        $this->assertTrue($asArray['reactions']);
        $this->assertTrue($asArray['send_images']);
        $this->assertTrue($asArray['send_documents']);
        $this->assertTrue($asArray['interactive_buttons']);
        $this->assertTrue($asArray['reaction_support']);
    }

    public function test_channel_detail_page_loads(): void
    {
        $this->actingAs($this->createOrganizationUser())
            ->get(route('channels.show', 'telegram'))
            ->assertOk()
            ->assertSeeText('Telegram')
            ->assertSeeText('Capabilities')
            ->assertSeeText('Health');
    }

    public function test_unknown_provider_is_handled_safely(): void
    {
        $this->actingAs($this->createOrganizationUser())
            ->get(route('channels.show', 'broadcast'))
            ->assertOk()
            ->assertSeeText('Broadcast')
            ->assertSeeText('Unsupported messaging provider [broadcast].');
    }

    private function createOrganizationUser(): User
    {
        $organization = Organization::query()->create([
            'name' => 'Lifecycle Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        return User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Lifecycle Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }
}
