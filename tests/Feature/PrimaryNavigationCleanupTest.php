<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('Catálogo de productos');
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

    public function test_primary_routes_are_available(): void
    {
        $user = User::factory()->state(['role' => User::ROLE_OWNER])->create();

        $this->actingAs($user)->get(route('orders.index'))->assertOk();
        $this->actingAs($user)->get(route('order-reviews.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_legacy_lottery_routes_no_longer_exist(): void
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
            '/closures',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
