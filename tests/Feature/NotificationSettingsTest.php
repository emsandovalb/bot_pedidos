<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\NotificationSetting;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationPolicyService;
use App\Services\NotificationTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_notification_settings(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->get(route('settings.notifications.index'))
            ->assertOk()
            ->assertSeeText('Configuración de notificaciones')
            ->assertSeeText('WhatsApp')
            ->assertSeeText('Telegram');

        $this->assertDatabaseCount('notification_settings', count(NotificationSetting::CHANNELS) * count(NotificationSetting::EVENTS));
    }

    public function test_user_can_update_whatsapp_event_settings(): void
    {
        $user = $this->createOrganizationUser();
        $this->actingAs($user)->get(route('settings.notifications.index'));

        $payload = $this->payloadFromCurrentSettings($user);
        $payload['settings'][NotificationSetting::CHANNEL_WHATSAPP][NotificationSetting::EVENT_ORDER_CREATED]['is_enabled'] = '0';
        $payload['settings'][NotificationSetting::CHANNEL_WHATSAPP][NotificationSetting::EVENT_ORDER_CREATED]['requires_open_service_window'] = '1';
        $payload['settings'][NotificationSetting::CHANNEL_WHATSAPP][NotificationSetting::EVENT_ORDER_CREATED]['use_template_if_window_closed'] = '1';
        $payload['settings'][NotificationSetting::CHANNEL_WHATSAPP][NotificationSetting::EVENT_ORDER_CREATED]['template_name'] = 'pedido_creado';
        $payload['settings'][NotificationSetting::CHANNEL_WHATSAPP][NotificationSetting::EVENT_ORDER_CREATED]['message_body'] = 'Pedido #{order_id} listo para seguimiento';

        $this->actingAs($user)
            ->post(route('settings.notifications.update'), $payload)
            ->assertRedirect(route('settings.notifications.index'));

        $setting = NotificationSetting::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', NotificationSetting::CHANNEL_WHATSAPP)
            ->where('event', NotificationSetting::EVENT_ORDER_CREATED)
            ->firstOrFail();

        $this->assertFalse($setting->is_enabled);
        $this->assertTrue($setting->requires_open_service_window);
        $this->assertTrue($setting->use_template_if_window_closed);
        $this->assertSame('pedido_creado', $setting->template_name);
        $this->assertSame('Pedido #{order_id} listo para seguimiento', $setting->message_body);
    }

    public function test_user_can_update_telegram_event_settings(): void
    {
        $user = $this->createOrganizationUser();
        $this->actingAs($user)->get(route('settings.notifications.index'));

        $payload = $this->payloadFromCurrentSettings($user);
        $payload['settings'][NotificationSetting::CHANNEL_TELEGRAM][NotificationSetting::EVENT_ORDER_DISPATCHED]['is_enabled'] = '0';
        $payload['settings'][NotificationSetting::CHANNEL_TELEGRAM][NotificationSetting::EVENT_ORDER_DISPATCHED]['message_body'] = 'Telegram dispatch notice';

        $this->actingAs($user)
            ->post(route('settings.notifications.update'), $payload)
            ->assertRedirect(route('settings.notifications.index'));

        $setting = NotificationSetting::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', NotificationSetting::CHANNEL_TELEGRAM)
            ->where('event', NotificationSetting::EVENT_ORDER_DISPATCHED)
            ->firstOrFail();

        $this->assertFalse($setting->is_enabled);
        $this->assertFalse($setting->requires_open_service_window);
        $this->assertFalse($setting->use_template_if_window_closed);
        $this->assertNull($setting->template_name);
        $this->assertSame('Telegram dispatch notice', $setting->message_body);
    }

    public function test_defaults_are_created_or_applied_safely(): void
    {
        $user = $this->createOrganizationUser();

        $this->assertDatabaseCount('notification_settings', 0);

        $this->actingAs($user)
            ->get(route('settings.notifications.index'))
            ->assertOk();

        $this->assertDatabaseCount('notification_settings', count(NotificationSetting::CHANNELS) * count(NotificationSetting::EVENTS));
        $this->assertDatabaseHas('notification_settings', [
            'organization_id' => $user->organization_id,
            'channel' => NotificationSetting::CHANNEL_WHATSAPP,
            'event' => NotificationSetting::EVENT_ORDER_CREATED,
            'is_enabled' => true,
        ]);
    }

    public function test_whatsapp_service_window_open_allows_notification(): void
    {
        $organization = $this->makeOrganization();
        $identity = $this->makeWhatsappIdentity($organization, now()->addHour());

        $result = app(NotificationPolicyService::class)->evaluate(
            organization: $organization,
            channel: NotificationSetting::CHANNEL_WHATSAPP,
            event: NotificationSetting::EVENT_ORDER_READY_FOR_DISPATCH,
            customerIdentity: $identity,
        );

        $this->assertTrue($result['should_send']);
        $this->assertFalse($result['requires_template']);
    }

    public function test_whatsapp_service_window_closed_blocks_notification_if_no_template(): void
    {
        $organization = $this->makeOrganization();
        $identity = $this->makeWhatsappIdentity($organization, now()->subHour());

        $result = app(NotificationPolicyService::class)->evaluate(
            organization: $organization,
            channel: NotificationSetting::CHANNEL_WHATSAPP,
            event: NotificationSetting::EVENT_ORDER_READY_FOR_DISPATCH,
            customerIdentity: $identity,
        );

        $this->assertFalse($result['should_send']);
        $this->assertFalse($result['requires_template']);
    }

    public function test_whatsapp_service_window_closed_allows_template_if_configured(): void
    {
        $organization = $this->makeOrganization();
        $identity = $this->makeWhatsappIdentity($organization, now()->subHour());

        NotificationSetting::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'channel' => NotificationSetting::CHANNEL_WHATSAPP,
                'event' => NotificationSetting::EVENT_ORDER_READY_FOR_DISPATCH,
            ],
            [
                'is_enabled' => true,
                'requires_open_service_window' => true,
                'use_template_if_window_closed' => true,
                'template_name' => 'pedido_listo',
                'message_body' => 'Tu pedido #{order_id} ya está listo.',
            ]
        );

        $result = app(NotificationPolicyService::class)->evaluate(
            organization: $organization,
            channel: NotificationSetting::CHANNEL_WHATSAPP,
            event: NotificationSetting::EVENT_ORDER_READY_FOR_DISPATCH,
            customerIdentity: $identity,
        );

        $this->assertTrue($result['should_send']);
        $this->assertTrue($result['requires_template']);
    }

    public function test_telegram_ignores_service_window(): void
    {
        $organization = $this->makeOrganization();
        $identity = $this->makeTelegramIdentity($organization, now()->subHours(10));

        $result = app(NotificationPolicyService::class)->evaluate(
            organization: $organization,
            channel: NotificationSetting::CHANNEL_TELEGRAM,
            event: NotificationSetting::EVENT_ORDER_DISPATCHED,
            customerIdentity: $identity,
        );

        $this->assertTrue($result['should_send']);
        $this->assertFalse($result['requires_template']);
    }

    public function test_template_renderer_replaces_placeholders(): void
    {
        $rendered = app(NotificationTemplateRenderer::class)->render(
            'Pedido {order_id} para {customer_name} en {business_name} quedo {status}.',
            [
                'order_id' => 42,
                'customer_name' => 'Maria',
                'status' => 'confirmado',
                'business_name' => 'Benditio',
            ]
        );

        $this->assertSame('Pedido 42 para Maria en Benditio quedo confirmado.', $rendered);
    }

    private function payloadFromCurrentSettings(User $user): array
    {
        $payload = ['settings' => []];

        NotificationSetting::query()
            ->where('organization_id', $user->organization_id)
            ->get()
            ->each(function (NotificationSetting $setting) use (&$payload): void {
                $payload['settings'][$setting->channel][$setting->event] = [
                    'is_enabled' => $setting->is_enabled ? '1' : '0',
                    'requires_open_service_window' => $setting->requires_open_service_window ? '1' : '0',
                    'use_template_if_window_closed' => $setting->use_template_if_window_closed ? '1' : '0',
                    'template_name' => $setting->template_name,
                    'message_body' => $setting->message_body,
                ];
            });

        return $payload;
    }

    private function makeOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Notification Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);
    }

    private function createOrganizationUser(): User
    {
        $organization = $this->makeOrganization();

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

        return $user;
    }

    private function makeWhatsappIdentity(Organization $organization, mixed $serviceWindowExpiresAt): CustomerIdentity
    {
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'WhatsApp Customer',
            'phone' => '+50255550001',
            'external_id' => null,
        ]);

        return CustomerIdentity::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'provider' => 'whatsapp',
            'external_user_id' => 'wa-user-1',
            'external_chat_id' => 'wa-chat-1',
            'provider_username' => 'customer.wa',
            'phone' => '+50255550001',
            'normalized_phone' => '+50255550001',
            'email' => null,
            'display_name' => 'WhatsApp Customer',
            'confidence_score' => 100,
            'is_primary' => true,
            'metadata_json' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_customer_message_at' => now(),
            'service_window_expires_at' => $serviceWindowExpiresAt,
        ]);
    }

    private function makeTelegramIdentity(Organization $organization, mixed $serviceWindowExpiresAt): CustomerIdentity
    {
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Telegram Customer',
            'phone' => '+50255550002',
            'external_id' => null,
        ]);

        return CustomerIdentity::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'provider' => 'telegram',
            'external_user_id' => 'tg-user-1',
            'external_chat_id' => 'tg-chat-1',
            'provider_username' => 'customer.tg',
            'phone' => '+50255550002',
            'normalized_phone' => '+50255550002',
            'email' => null,
            'display_name' => 'Telegram Customer',
            'confidence_score' => 100,
            'is_primary' => true,
            'metadata_json' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_customer_message_at' => now(),
            'service_window_expires_at' => $serviceWindowExpiresAt,
        ]);
    }
}
