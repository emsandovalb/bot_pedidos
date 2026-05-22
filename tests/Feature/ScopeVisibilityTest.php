<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScopeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_see_all_organization_branches_and_messages(): void
    {
        $organization = Organization::create([
            'name' => 'Visibility Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branchOne = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'North Branch',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501001',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $branchTwo = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'South Branch',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501002',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $viewer = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_VIEWER,
            'name' => 'Viewer',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        IncomingMessage::create([
            'organization_id' => $organization->id,
            'branch_id' => $branchOne->id,
            'customer_id' => null,
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'from_identifier' => '+50255510001',
            'to_identifier' => $branchOne->channel_identifier,
            'raw_text' => '1000 al 28',
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        IncomingMessage::create([
            'organization_id' => $organization->id,
            'branch_id' => $branchTwo->id,
            'customer_id' => null,
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'from_identifier' => '+50255510002',
            'to_identifier' => $branchTwo->channel_identifier,
            'raw_text' => '500 numero 99',
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        $this->actingAs($viewer)->get(route('branches.index'))
            ->assertOk()
            ->assertSeeText('North Branch')
            ->assertSeeText('South Branch');

        $this->actingAs($viewer)->get(route('incoming-messages.index'))
            ->assertOk()
            ->assertSeeText('1000 al 28')
            ->assertSeeText('500 numero 99');

        $this->actingAs($viewer)->get(route('simulator.index'))
            ->assertOk()
            ->assertSeeText('North Branch')
            ->assertSeeText('South Branch');
    }
}
