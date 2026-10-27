<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OriaConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Configuración → Oria: conectar el negocio, el plan que decide Oria y "Pagar servicios" con Plus. */
class OriaPlusTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://oria.test/api';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oria.api_url' => self::API, 'oria.partner_key' => 'oria_test-key', 'oria.plan_ttl_minutes' => 10]);

        $organization = Organization::query()->create(['name' => 'Ferretería El Clavo', 'status' => Organization::STATUS_ACTIVE]);
        $this->owner = $this->userWithRole($organization, User::ROLE_OWNER);
    }

    private function userWithRole(Organization $organization, string $role): User
    {
        return User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => $role,
            'name' => ucfirst($role),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }

    private function connection(string $plan = OriaConnection::PLAN_BASIC, array $attributes = []): OriaConnection
    {
        return OriaConnection::query()->create([
            'organization_id' => $this->owner->organization_id,
            'oria_link_id' => 7,
            'plan' => $plan,
            'account_name' => 'Marta Solís',
            'account_email' => 'marta@example.com',
            'plan_checked_at' => now(),
            ...$attributes,
        ]);
    }

    /** @return array<string, mixed> */
    private function link(string $plan = 'basico', bool $active = true): array
    {
        return [
            'id' => 7,
            'externalId' => (string) $this->owner->organization_id,
            'plan' => $plan,
            'active' => $active,
            'account' => ['name' => 'Marta Solís', 'email' => 'marta@example.com'],
        ];
    }

    public function test_the_page_says_when_oria_is_not_configured(): void
    {
        config(['oria.partner_key' => null]);

        $this->actingAs($this->owner)->get(route('settings.oria.index'))
            ->assertOk()
            ->assertSeeText('Oria no está configurada');
    }

    public function test_connecting_sends_the_owner_to_oria_with_a_state(): void
    {
        Http::fake([self::API.'/partner/link-requests' => Http::response(['approveUrl' => 'https://oria.test/vincular/abc', 'expiresAt' => now()->toIso8601String()], 201)]);

        $this->actingAs($this->owner)->get(route('settings.oria.index'))->assertSeeText('Conectar con Oria');
        $response = $this->actingAs($this->owner)->post(route('settings.oria.connect'));

        $response->assertRedirect('https://oria.test/vincular/abc');
        $state = session('oria.link_state');
        $this->assertIsString($state);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer oria_test-key')
            && $request['externalId'] === (string) $this->owner->organization_id
            && $request['externalName'] === 'Ferretería El Clavo'
            && $request['returnUrl'] === route('settings.oria.callback')
            && $request['state'] === $state);
    }

    public function test_when_oria_is_down_connecting_says_so(): void
    {
        Http::fake([self::API.'/*' => Http::response(null, 500)]);

        $this->actingAs($this->owner)->post(route('settings.oria.connect'))
            ->assertRedirect(route('settings.oria.index'))
            ->assertSessionHas('error');
    }

    public function test_coming_back_trades_the_code_for_the_link(): void
    {
        Http::fake([self::API.'/partner/links/exchange' => Http::response($this->link('plus'))]);

        $this->actingAs($this->owner)->withSession(['oria.link_state' => 'st-1'])
            ->get(route('settings.oria.callback', ['code' => 'grant-code', 'state' => 'st-1']))
            ->assertRedirect(route('settings.oria.index'))
            ->assertSessionHas('status', 'Listo: el negocio quedó conectado con la cuenta marta@example.com de Oria.')
            ->assertSessionMissing('oria.link_state');

        $connection = OriaConnection::query()->sole();
        $this->assertSame([7, 'plus', 'marta@example.com', $this->owner->id], [$connection->oria_link_id, $connection->plan, $connection->account_email, $connection->connected_by]);
        Http::assertSent(fn (Request $request) => $request['code'] === 'grant-code');
    }

    public function test_a_return_with_another_state_is_refused(): void
    {
        Http::fake();

        $this->actingAs($this->owner)->withSession(['oria.link_state' => 'st-1'])
            ->get(route('settings.oria.callback', ['code' => 'grant-code', 'state' => 'otro']))
            ->assertRedirect(route('settings.oria.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, OriaConnection::query()->count());
        Http::assertNothingSent();
    }

    public function test_saying_no_in_oria_leaves_it_unconnected(): void
    {
        $this->actingAs($this->owner)->withSession(['oria.link_state' => 'st-1'])
            ->get(route('settings.oria.callback', ['error' => 'denied', 'state' => 'st-1']))
            ->assertSessionHas('status', 'No se conectó con Oria.');

        $this->assertSame(0, OriaConnection::query()->count());
    }

    public function test_pay_services_shows_only_on_plus_and_opens_oria(): void
    {
        $connection = $this->connection();

        $this->actingAs($this->owner)->get(route('dashboard'))->assertDontSeeText('Pagar servicios');
        $this->actingAs($this->owner)->get(route('settings.oria.pay-services'))
            ->assertRedirect(route('settings.oria.index'))
            ->assertSessionHas('error', 'Pagar servicios es parte del plan Plus de Oria.');

        $connection->update(['plan' => OriaConnection::PLAN_PLUS]);
        Http::fake([self::API.'/partner/links/7/sign-in-links' => Http::response(['url' => 'https://oria.test/entrar/xyz', 'expiresAt' => now()->toIso8601String()], 201)]);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertSeeText('Pagar servicios');
        $this->actingAs($this->owner)->get(route('settings.oria.pay-services'))->assertRedirect('https://oria.test/entrar/xyz');
    }

    public function test_if_oria_says_no_the_plan_is_checked_again(): void
    {
        $connection = $this->connection(OriaConnection::PLAN_PLUS);
        Http::fake([self::API.'/partner/links/7/sign-in-links' => Http::response(['message' => 'x', 'errors' => ['plan' => ['Abrir Oria desde la app es parte del plan Plus.']]], 422)]);

        $this->actingAs($this->owner)->get(route('settings.oria.pay-services'))
            ->assertRedirect(route('settings.oria.index'))
            ->assertSessionHas('error', 'Abrir Oria desde la app es parte del plan Plus.');

        $this->assertNull($connection->refresh()->plan_checked_at);
    }

    public function test_an_old_plan_is_asked_again_and_follows_oria(): void
    {
        $connection = $this->connection(OriaConnection::PLAN_BASIC, ['plan_checked_at' => now()->subMinutes(11)]);
        Http::fake([self::API.'/partner/links/7' => Http::response($this->link('plus'))]);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertSeeText('Pagar servicios');

        $this->assertSame('plus', $connection->refresh()->plan);
        Http::assertSentCount(1);

        // Recién revisado: no se vuelve a preguntar.
        $this->actingAs($this->owner)->get(route('dashboard'));
        Http::assertSentCount(1);
    }

    public function test_a_link_oria_closed_disconnects_the_business(): void
    {
        $this->connection(OriaConnection::PLAN_PLUS, ['plan_checked_at' => null]);
        Http::fake([self::API.'/partner/links/7' => Http::response($this->link('plus', active: false))]);

        $this->actingAs($this->owner)->get(route('settings.oria.index'))->assertSeeText('Conectar con Oria');

        $this->assertSame(0, OriaConnection::query()->count());
    }

    public function test_while_oria_is_down_the_last_plan_stays_and_it_waits_to_retry(): void
    {
        $connection = $this->connection(OriaConnection::PLAN_PLUS, ['plan_checked_at' => null]);
        Http::fake([self::API.'/*' => Http::response(null, 503)]);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertSeeText('Pagar servicios');
        $this->actingAs($this->owner)->get(route('dashboard'));

        $this->assertNotNull($connection->refresh()->plan_checked_at);
        Http::assertSentCount(1);
    }

    public function test_disconnecting_tells_oria_and_forgets_the_link(): void
    {
        $this->connection();
        Http::fake([self::API.'/partner/links/7' => Http::response($this->link(active: false))]);

        $this->actingAs($this->owner)->delete(route('settings.oria.disconnect'))
            ->assertSessionHas('status', 'El negocio ya no está conectado con Oria.');

        $this->assertSame(0, OriaConnection::query()->count());
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === self::API.'/partner/links/7');
    }

    public function test_sellers_do_not_see_oria(): void
    {
        $this->connection(OriaConnection::PLAN_PLUS);
        $seller = $this->userWithRole(Organization::query()->findOrFail($this->owner->organization_id), User::ROLE_SELLER);

        $this->actingAs($seller)->get(route('dashboard'))->assertDontSeeText('Pagar servicios');
        $this->actingAs($seller)->get(route('settings.oria.pay-services'))->assertForbidden();
    }
}
