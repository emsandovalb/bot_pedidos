<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_pages_require_authentication(): void
    {
        $this->get(route('pilot.checklist'))->assertRedirect(route('login'));
        $this->get(route('pilot.script'))->assertRedirect(route('login'));
        $this->get(route('pilot.guide'))->assertRedirect(route('login'));
    }

    public function test_owner_seller_and_viewer_can_access_pilot_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = [
            User::where('email', 'owner@local.test')->firstOrFail(),
            User::where('email', 'admin@local.test')->firstOrFail(),
            User::where('email', 'seller-1@local.test')->firstOrFail(),
            User::where('email', 'viewer@local.test')->firstOrFail(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('pilot.checklist'))->assertOk();
            $this->actingAs($user)->get(route('pilot.script'))->assertOk();
            $this->actingAs($user)->get(route('pilot.guide'))->assertOk();
        }
    }

    public function test_viewer_user_exists_after_seeding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'viewer@local.test',
            'role' => User::ROLE_VIEWER,
        ]);
    }
}
