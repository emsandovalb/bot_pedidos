<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NumberBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_draws_exist(): void
    {
        $this->seed();

        $this->assertDatabaseCount('draws', 4);
        $this->assertDatabaseHas('draws', ['name' => '12:00 md']);
        $this->assertDatabaseHas('draws', ['name' => '2:00 pm']);
        $this->assertDatabaseHas('draws', ['name' => '5:00 pm']);
        $this->assertDatabaseHas('draws', ['name' => '7:00 pm']);
    }

    public function test_number_board_shows_00_to_99(): void
    {
        [$owner] = $this->makeOrganizationWithBranchesAndDraws();

        $this->actingAs($owner)->get(route('numbers.index'))
            ->assertOk()
            ->assertSeeText('00')
            ->assertSeeText('99');
    }

    public function test_number_board_shows_no_limit_state_when_no_limit_exists(): void
    {
        [$owner] = $this->makeOrganizationWithBranchesAndDraws();

        $this->actingAs($owner)->get(route('numbers.index'))
            ->assertOk()
            ->assertSeeText('no_limit')
            ->assertSeeText('available');
    }

    public function test_owner_sees_all_branches_on_number_board(): void
    {
        [$owner, , $branchOne, $branchTwo] = $this->makeOrganizationWithBranchesAndDraws();

        $this->actingAs($owner)->get(route('numbers.index'))
            ->assertOk()
            ->assertSeeText($branchOne->name)
            ->assertSeeText($branchTwo->name);
    }

    public function test_seller_sees_only_assigned_branch(): void
    {
        [, $seller, $branchOne, $branchTwo] = $this->makeOrganizationWithBranchesAndDraws();

        $this->actingAs($seller)->get(route('numbers.index'))
            ->assertOk()
            ->assertSeeText($branchOne->name)
            ->assertDontSeeText($branchTwo->name);
    }

    public function test_board_calculates_separate_status_buckets_and_ignores_rejected_amounts(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'max_amount' => 100,
        ]);

        $this->createRequest($owner, $branchOne, $draw, '28', 10, IntakeRequest::STATUS_CONFIRMED);
        $this->createRequest($owner, $branchOne, $draw, '28', 15, IntakeRequest::STATUS_PENDING);
        $this->createRequest($owner, $branchOne, $draw, '28', 20, IntakeRequest::STATUS_NEEDS_REVIEW);
        $this->createRequest($owner, $branchOne, $draw, '28', 25, IntakeRequest::STATUS_REJECTED);

        $this->actingAs($owner)->get(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]))
            ->assertOk()
            ->assertSeeText('₡10.00')
            ->assertSeeText('₡15.00')
            ->assertSeeText('₡20.00')
            ->assertSeeText('₡25.00')
            ->assertSeeText('₡45.00')
            ->assertSeeText('₡55.00')
            ->assertSeeText('45.0%')
            ->assertSeeText('available');
    }

    public function test_board_status_thresholds_show_warning_full_and_over_limit(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        foreach ([
            ['number' => '10', 'amount' => 79],
            ['number' => '11', 'amount' => 80],
            ['number' => '12', 'amount' => 100],
            ['number' => '13', 'amount' => 120],
        ] as $row) {
            NumberLimit::create([
                'organization_id' => $owner->organization_id,
                'branch_id' => $branchOne->id,
                'draw_id' => $draw->id,
                'number' => $row['number'],
                'max_amount' => 100,
            ]);

            $this->createRequest($owner, $branchOne, $draw, $row['number'], $row['amount'], IntakeRequest::STATUS_CONFIRMED);
        }

        $this->actingAs($owner)->get(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]))
            ->assertOk()
            ->assertSeeText('warning')
            ->assertSeeText('full')
            ->assertSeeText('over limit');
    }

    public function test_number_board_groups_numbers_by_tens(): void
    {
        [$owner] = $this->makeOrganizationWithBranchesAndDraws();

        $this->actingAs($owner)->get(route('numbers.index'))
            ->assertOk()
            ->assertSeeText('00-09')
            ->assertSeeText('10-19')
            ->assertSeeText('20-29')
            ->assertSeeText('30-39')
            ->assertSeeText('40-49')
            ->assertSeeText('50-59')
            ->assertSeeText('60-69')
            ->assertSeeText('70-79')
            ->assertSeeText('80-89')
            ->assertSeeText('90-99');
    }

    public function test_number_board_shows_limit_values_after_creation(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'max_amount' => 250,
        ]);

        $this->createRequest($owner, $branchOne, $draw, '28', 75, IntakeRequest::STATUS_CONFIRMED);

        $this->actingAs($owner)->get(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]))
            ->assertOk()
            ->assertSeeText('₡250.00')
            ->assertSeeText('₡175.00')
            ->assertSeeText('available');
    }

    public function test_manual_request_within_limit_creates_pending(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'max_amount' => 1000,
        ]);

        $this->actingAs($owner)->post(route('numbers.store'), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
            'customer_phone' => '+50255510010',
            'notes' => 'Manual board entry',
        ])->assertRedirect(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'detected_amount' => 100,
            'status' => IntakeRequest::STATUS_PENDING,
            'notes' => 'Manual board entry',
        ]);
    }

    public function test_manual_request_can_store_customer_name(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        $this->actingAs($owner)->post(route('numbers.store'), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
            'customer_name' => 'Maria Lopez',
            'customer_phone' => '+50255510012',
            'notes' => null,
        ])->assertRedirect(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]));

        $this->assertDatabaseHas('customers', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'name' => 'Maria Lopez',
            'phone' => '+50255510012',
        ]);
    }

    public function test_manual_request_exceeding_limit_creates_needs_review(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        NumberLimit::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'max_amount' => 1000,
        ]);

        $this->createRequest($owner, $branchOne, $draw, '28', 900, IntakeRequest::STATUS_PENDING);

        $this->actingAs($owner)->post(route('numbers.store'), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 200,
            'customer_phone' => '+50255510011',
            'notes' => null,
        ])->assertRedirect(route('numbers.index', ['branch_id' => $branchOne->id, 'draw_id' => $draw->id]));

        $request = IntakeRequest::query()
            ->where('organization_id', $owner->organization_id)
            ->where('branch_id', $branchOne->id)
            ->where('draw_id', $draw->id)
            ->where('detected_number', '28')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $request->status);
        $this->assertStringContainsString('Limit warning', (string) $request->notes);
    }

    public function test_viewer_cannot_create_manual_request(): void
    {
        [$owner, , $branchOne] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        $viewer = $this->createUser($owner->organization_id, null, User::ROLE_VIEWER, 'Viewer');

        $this->actingAs($viewer)->post(route('numbers.store'), [
            'branch_id' => $branchOne->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
            'customer_phone' => null,
            'notes' => null,
        ])->assertForbidden();
    }

    public function test_seller_cannot_create_manual_request_for_another_branch(): void
    {
        [$owner, $seller, $branchOne, $branchTwo] = $this->makeOrganizationWithBranchesAndDraws();
        $draw = $this->drawByName($owner, '2:00 pm');

        $this->actingAs($seller)->post(route('numbers.store'), [
            'branch_id' => $branchTwo->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
            'customer_phone' => null,
            'notes' => null,
        ])->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('requests', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'detected_amount' => 100,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Branch, 3: Branch}
     */
    private function makeOrganizationWithBranchesAndDraws(): array
    {
        $organization = Organization::create([
            'name' => 'Numbers Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branchOne = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch One',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255500101',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $branchTwo = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch Two',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255500102',
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

    private function createRequest(User $user, Branch $branch, Draw $draw, string $number, float $amount, string $status): IntakeRequest
    {
        return IntakeRequest::create([
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => $number,
            'detected_amount' => $amount,
            'raw_text' => "{$amount} on {$number}",
            'status' => $status,
            'notes' => null,
        ]);
    }
}
