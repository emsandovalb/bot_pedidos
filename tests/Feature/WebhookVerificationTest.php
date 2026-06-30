<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_whatsapp_verification_returns_challenge(): void
    {
        $connection = $this->saveWhatsAppConfiguration();

        $response = $this->get(route('webhooks.show', [
            'provider' => 'whatsapp',
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'verify-token-value',
            'hub.challenge' => 'challenge-123',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('challenge-123');

        $connection->refresh();

        $this->assertSame('verified', $connection->webhook_status);
        $this->assertSame(ChannelConnection::STATUS_VERIFIED, $connection->provider_status);

        $event = WebhookEvent::query()->latest('id')->firstOrFail();

        $this->assertSame('whatsapp', $event->provider);
        $this->assertSame('verification', $event->event_type);
        $this->assertSame('GET', $event->method);
        $this->assertSame('200', $event->status);
        $this->assertSame($connection->organization_id, $event->organization_id);
        $this->assertSame('subscribe', $event->payload_json['hub_mode'] ?? null);
        $this->assertTrue((bool) ($event->payload_json['has_verify_token'] ?? false));
        $this->assertStringNotContainsString('verify-token-value', json_encode($event->payload_json));
        $this->assertStringNotContainsString('challenge-123', json_encode($event->payload_json));
    }

    public function test_invalid_token_returns_403(): void
    {
        $this->saveWhatsAppConfiguration();

        $response = $this->get(route('webhooks.show', [
            'provider' => 'whatsapp',
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong-token',
            'hub.challenge' => 'challenge-123',
        ]));

        $response->assertStatus(403)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $event = WebhookEvent::query()->latest('id')->firstOrFail();

        $this->assertSame('403', $event->status);
        $this->assertSame('verification', $event->event_type);
        $this->assertStringNotContainsString('wrong-token', json_encode($event->payload_json));
    }

    public function test_wrong_mode_returns_403(): void
    {
        $this->saveWhatsAppConfiguration();

        $response = $this->get(route('webhooks.show', [
            'provider' => 'whatsapp',
            'hub.mode' => 'unsubscribe',
            'hub.verify_token' => 'verify-token-value',
            'hub.challenge' => 'challenge-123',
        ]));

        $response->assertStatus(403)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $event = WebhookEvent::query()->latest('id')->firstOrFail();

        $this->assertSame('403', $event->status);
        $this->assertSame('unsubscribe', $event->payload_json['hub_mode'] ?? null);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->get(route('webhooks.show', ['provider' => 'unknown-provider']))
            ->assertStatus(404);
    }

    public function test_telegram_returns_501(): void
    {
        $this->get(route('webhooks.show', [
            'provider' => 'telegram',
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'anything',
            'hub.challenge' => 'challenge-123',
        ]))
            ->assertStatus(501)
            ->assertSee('Telegram webhook verification is not implemented yet.');
    }

    public function test_instagram_returns_501(): void
    {
        $this->post(route('webhooks.store', ['provider' => 'instagram']))
            ->assertStatus(501)
            ->assertSee('Instagram webhook ingestion is not implemented yet.');
    }

    private function saveWhatsAppConfiguration(): ChannelConnection
    {
        $organization = Organization::query()->create([
            'name' => 'Webhook Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Webhook Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        return app(WhatsAppConfigurationService::class)->saveConfiguration($organization->id, [
            'provider_app_id' => '123456789012345',
            'provider_app_secret' => 'app-secret-value',
            'provider_access_token' => 'access-token-value',
            'provider_verify_token' => 'verify-token-value',
            'provider_webhook_secret' => 'webhook-secret-value',
            'provider_phone_number_id' => '555550000000001',
            'provider_business_account_id' => '555550000000002',
            'provider_api_version' => 'v21.0',
            'provider_business_name' => 'Webhook Org',
            'provider_business_timezone' => 'America/Guatemala',
            'provider_business_country' => 'GT',
        ]);
    }
}
