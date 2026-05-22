<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\NumberLimit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NumberLimitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_number_limits(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        $this->actingAs($owner)->post(route('limits.store'), [
            'mode' => 'single',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '07',
            'max_amount' => 100,
        ])->assertRedirect(route('limits.index', [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '07',
        ]));

        $limit = NumberLimit::query()
            ->where('organization_id', $owner->organization_id)
            ->where('branch_id', $branchOne->id)
            ->where('draw_id', $draw->id)
            ->where('number', '07')
            ->firstOrFail();

        $this->actingAs($owner)->put(route('limits.update', $limit), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '08',
            'max_amount' => 150,
        ])->assertRedirect(route('limits.index', [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '08',
        ]));

        $this->assertDatabaseHas('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '08',
            'max_amount' => '150.00',
        ]);

        $this->actingAs($owner)->delete(route('limits.delete', $limit->fresh()))
            ->assertRedirect(route('limits.index', [
                'branch_id' => $branchOne->id,
                'draw_id' => $draw->id,
                'number' => '08',
            ]));

        $this->assertDatabaseMissing('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '08',
        ]);
    }

    public function test_admin_can_manage_number_limits(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '5:00 pm');
        $admin = $this->createUser($owner->organization_id, null, User::ROLE_ADMIN, 'Admin');

        $this->actingAs($admin)->post(route('limits.store'), [
            'mode' => 'single',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '11',
            'max_amount' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '11',
            'max_amount' => '120.00',
        ]);
    }

    public function test_seller_cannot_manage_number_limits(): void
    {
        [$owner, $seller, $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');
        $limit = NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '12',
            'max_amount' => 100,
        ]);

        $this->actingAs($seller)->get(route('limits.create'))->assertForbidden();
        $this->actingAs($seller)->post(route('limits.store'), [
            'mode' => 'single',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '13',
            'max_amount' => 100,
        ])->assertForbidden();
        $this->actingAs($seller)->put(route('limits.update', $limit), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '14',
            'max_amount' => 100,
        ])->assertForbidden();
        $this->actingAs($seller)->delete(route('limits.delete', $limit))->assertForbidden();
    }

    public function test_viewer_cannot_manage_number_limits(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');
        $viewer = $this->createUser($owner->organization_id, null, User::ROLE_VIEWER, 'Viewer');
        $limit = NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '21',
            'max_amount' => 100,
        ]);

        $this->actingAs($viewer)->get(route('limits.index'))->assertOk();
        $this->actingAs($viewer)->get(route('limits.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('limits.store'), [
            'mode' => 'single',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '22',
            'max_amount' => 100,
        ])->assertForbidden();
        $this->actingAs($viewer)->put(route('limits.update', $limit), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '23',
            'max_amount' => 100,
        ])->assertForbidden();
        $this->actingAs($viewer)->delete(route('limits.delete', $limit))->assertForbidden();
    }

    public function test_seller_can_view_assigned_branch_limits(): void
    {
        [$owner, $seller, $branchOne, $branchTwo] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '31',
            'max_amount' => 100,
        ]);

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'draw_id' => $draw->id,
            'number' => '32',
            'max_amount' => 100,
        ]);

        $this->actingAs($seller)->get(route('limits.index'))
            ->assertOk()
            ->assertSeeText($branchOne->name)
            ->assertDontSeeText($branchTwo->name);
    }

    public function test_unique_limit_validation_works(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '40',
            'max_amount' => 100,
        ]);

        $this->actingAs($owner)->post(route('limits.store'), [
            'mode' => 'single',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '40',
            'max_amount' => 150,
        ])->assertSessionHasErrors('number');
    }

    public function test_bulk_create_applies_limits_to_all_numbers(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        $this->actingAs($owner)->post(route('limits.store'), [
            'mode' => 'bulk',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'max_amount' => 200,
            'apply_to' => 'all',
        ])->assertRedirect();

        $this->assertDatabaseCount('number_limits', 100);
        $this->assertDatabaseHas('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '00',
            'max_amount' => '200.00',
        ]);
        $this->assertDatabaseHas('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '99',
            'max_amount' => '200.00',
        ]);
    }

    public function test_bulk_create_only_missing_does_not_overwrite_existing_limits(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '07',
            'max_amount' => 55,
        ]);

        $this->actingAs($owner)->post(route('limits.store'), [
            'mode' => 'bulk',
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'max_amount' => 200,
            'apply_to' => 'missing',
        ])->assertRedirect();

        $this->assertDatabaseCount('number_limits', 100);
        $this->assertDatabaseHas('number_limits', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '07',
            'max_amount' => '55.00',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Branch, 3: Branch}
     */
    private function makeOrganizationWithBranchesAndDraws(): array
    {
        $organization = Organization::create([
            'name' => 'Limits Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branchOne = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch One',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501101',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $branchTwo = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch Two',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501102',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        collect([
            ['name' => '12:00 md', 'draw_time' => '12:00:00'],
            ['name' => '2:00 pm', 'draw_time' => '14:00:00'],
            ['name' => '5:00 pm', 'draw_time' => '17:00:00'],
            ['name' => '7:00 pm', 'draw_time' => '19:00:00'],
        ])->each(function (array $drawData) use ($organization): void {
            Draw::create([
                'organization_id' => $organization->id,
                'name' => $drawData['name'],
                'draw_time' => $drawData['draw_time'],
                'status' => Draw::STATUS_ACTIVE,
            ]);
        });

        $owner = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $owner->id]);

        $seller = User::create([
            'organization_id' => $organization->id,
            'branch_id' => $branchOne->id,
            'role' => User::ROLE_SELLER,
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        return [$owner->fresh(), $seller->fresh(), $branchOne->fresh(), $branchTwo->fresh()];
    }

    private function createUser(int $organizationId, ?int $branchId, string $role, string $name): User
    {
        return User::create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'role' => $role,
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }

    private function drawByName(User $user, string $name): Draw
    {
        return Draw::query()
            ->where('organization_id', $user->organization_id)
            ->where('name', $name)
            ->firstOrFail();
    }
}
