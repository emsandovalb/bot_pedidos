<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedRequests(): array
    {
        return [
            'whatsapp setup' => ['get', '/channels/whatsapp'],
            'whatsapp configuration' => ['get', '/channels/whatsapp/configuration'],
            'save whatsapp configuration' => ['post', '/channels/whatsapp/configuration'],
            'verify endpoint' => ['post', '/channels/whatsapp/verify-endpoint'],
            'onboarding' => ['post', '/channels/whatsapp/onboarding'],
            'connect channel' => ['post', '/channels/whatsapp/connect'],
            'disconnect channel' => ['post', '/channels/whatsapp/disconnect'],
            'create setup request' => ['post', '/setup-requests'],
            'notification settings' => ['get', '/settings/notifications'],
            'save notification settings' => ['post', '/settings/notifications'],
            'oria settings' => ['get', '/settings/oria'],
            'connect oria' => ['post', '/settings/oria/connect'],
            'oria callback' => ['get', '/settings/oria/callback'],
            'disconnect oria' => ['delete', '/settings/oria'],
            'pay services' => ['get', '/settings/oria/pay-services'],
        ];
    }

    #[DataProvider('protectedRequests')]
    public function test_sellers_cannot_manage_settings(string $method, string $uri): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_SELLER))
            ->{$method}($uri)
            ->assertForbidden();
    }

    #[DataProvider('protectedRequests')]
    public function test_viewers_cannot_manage_settings(string $method, string $uri): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_VIEWER))
            ->{$method}($uri)
            ->assertForbidden();
    }

    public function test_owner_and_admin_can_open_settings_pages(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_ADMIN] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/channels/whatsapp/configuration')->assertOk();
            $this->actingAs($user)->get('/settings/notifications')->assertOk();
        }
    }

    public function test_seller_does_not_see_settings_actions(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_SELLER))
            ->get('/channels/whatsapp')
            ->assertForbidden();

        $this->actingAs($this->userWithRole(User::ROLE_SELLER))
            ->get('/channels/whatsapp/status')
            ->assertOk()
            ->assertDontSee(route('channels.whatsapp.configuration'))
            ->assertDontSee(route('settings.notifications.index'));
    }

    public function test_sellers_cannot_use_the_developer_toolkit(): void
    {
        config(['features.developer_toolkit_enabled' => true]);

        $seller = $this->userWithRole(User::ROLE_SELLER);

        $this->actingAs($seller)->get('/developer/webhook-simulator')->assertForbidden();
        $this->actingAs($seller)->post('/developer/toolkit/reset-demo-data')->assertForbidden();
    }

    public function test_debug_mode_alone_does_not_expose_the_developer_toolkit(): void
    {
        config(['app.debug' => true, 'features.developer_toolkit_enabled' => false]);

        $this->actingAs($this->userWithRole(User::ROLE_OWNER))
            ->get('/developer/webhook-simulator')
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $organization = Organization::create([
            'name' => 'Settings Org '.$role,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        return User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => $role,
            'name' => ucfirst($role),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }
}
