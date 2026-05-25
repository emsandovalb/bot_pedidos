<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PrimaryNavigationCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_navigation_hides_legacy_lottery_links(): void
    {
        $user = User::factory()->state(['role' => User::ROLE_OWNER])->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pedidos pendientes');
        $response->assertSee('Catalogo de productos');
        $response->assertDontSee('/requests');
        $response->assertDontSee('/numbers');
        $response->assertDontSee('/limits');
        $response->assertDontSee('/simulator');
        $response->assertDontSee('/pilot-checklist');
        $response->assertDontSee('/pilot-script');
        $response->assertDontSee('/operator-guide');
        $response->assertDontSee('Sorteo');
        $response->assertDontSee('apuesta');
        $response->assertDontSee('loteria');
    }

    public function test_primary_routes_remain_available_when_legacy_routes_are_disabled(): void
    {
        $user = User::factory()->state(['role' => User::ROLE_OWNER])->create();

        $this->actingAs($user)->get(route('orders.index'))->assertOk();
        $this->actingAs($user)->get(route('order-reviews.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_legacy_lottery_routes_are_not_registered_by_default(): void
    {
        $user = User::factory()->state(['role' => User::ROLE_OWNER])->create();

        $this->actingAs($user);

        foreach ([
            '/requests',
            '/numbers',
            '/limits',
            '/simulator',
            '/pilot-checklist',
            '/pilot-script',
            '/operator-guide',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_legacy_lottery_routes_can_be_temporarily_enabled(): void
    {
        putenv('LEGACY_LOTTERY_ROUTES_ENABLED=true');
        $_ENV['LEGACY_LOTTERY_ROUTES_ENABLED'] = 'true';
        $_SERVER['LEGACY_LOTTERY_ROUTES_ENABLED'] = 'true';

        $this->refreshApplication();
        $this->assertTrue(Route::has('intake-requests.index'));
        $this->assertTrue(Route::has('numbers.index'));
        $this->assertTrue(Route::has('limits.index'));
        $this->assertTrue(Route::has('simulator.index'));
        $this->assertTrue(Route::has('pilot.checklist'));
        $this->assertTrue(Route::has('pilot.script'));
        $this->assertTrue(Route::has('pilot.guide'));

        putenv('LEGACY_LOTTERY_ROUTES_ENABLED');
        unset($_ENV['LEGACY_LOTTERY_ROUTES_ENABLED'], $_SERVER['LEGACY_LOTTERY_ROUTES_ENABLED']);
        $this->refreshApplication();
    }
}
