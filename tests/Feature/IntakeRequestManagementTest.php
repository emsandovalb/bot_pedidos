<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntakeRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_confirm_any_organization_request(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();

        $response = $this->actingAs($owner)->post(route('intake-requests.confirm', $request));

        $response->assertRedirect(route('intake-requests.index'));
        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => IntakeRequest::STATUS_CONFIRMED,
            'confirmed_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('intake_request_events', [
            'intake_request_id' => $request->id,
            'event_type' => IntakeRequest::EVENT_CONFIRMED,
            'notes' => 'Request confirmed.',
        ]);

        $this->assertTrue(
            $owner->fresh()->confirmedRequests()->whereKey($request->id)->exists()
        );
    }

    public function test_seller_can_confirm_only_their_branch_request(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();
        $seller = User::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'role' => User::ROLE_SELLER,
            'name' => 'Seller One',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $branchTwoRequest = IntakeRequest::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '2:00 pm')->value('id'),
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => '28',
            'detected_amount' => 1000,
            'raw_text' => '1000 al 28 2pm',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($seller)->post(route('intake-requests.confirm', $branchTwoRequest))->assertForbidden();

        $sellerRequest = IntakeRequest::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '5:00 pm')->value('id'),
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => '45',
            'detected_amount' => 500,
            'raw_text' => '500 numero 45 5pm',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($seller)->post(route('intake-requests.confirm', $sellerRequest))->assertRedirect(route('intake-requests.index'));
        $this->assertDatabaseHas('requests', [
            'id' => $sellerRequest->id,
            'status' => IntakeRequest::STATUS_CONFIRMED,
            'confirmed_by' => $seller->id,
        ]);

        $this->assertDatabaseHas('intake_request_events', [
            'intake_request_id' => $sellerRequest->id,
            'event_type' => IntakeRequest::EVENT_CONFIRMED,
        ]);
    }

    public function test_viewer_cannot_confirm(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();
        $viewer = User::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => null,
            'role' => User::ROLE_VIEWER,
            'name' => 'Viewer',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($viewer)->post(route('intake-requests.confirm', $request))->assertForbidden();
        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => IntakeRequest::STATUS_PENDING,
        ]);
    }

    public function test_needs_review_request_can_be_edited_then_confirmed(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest(status: IntakeRequest::STATUS_NEEDS_REVIEW);

        $this->actingAs($owner)->patch(route('intake-requests.update', $request), [
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '5:00 pm')->value('id'),
            'detected_number' => '77',
            'detected_amount' => 1250,
            'notes' => 'Updated after review.',
        ])->assertRedirect(route('intake-requests.index'));

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'detected_number' => '77',
            'detected_amount' => 1250,
            'notes' => 'Updated after review.',
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '5:00 pm')->value('id'),
        ]);

        $this->assertDatabaseHas('intake_request_events', [
            'intake_request_id' => $request->id,
            'event_type' => IntakeRequest::EVENT_EDITED,
            'notes' => 'Manual edit from request detail page.',
        ]);

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect(route('intake-requests.index'));

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => IntakeRequest::STATUS_CONFIRMED,
            'confirmed_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('intake_request_events', [
            'intake_request_id' => $request->id,
            'event_type' => IntakeRequest::EVENT_CONFIRMED,
        ]);
    }

    public function test_requests_can_be_filtered_by_draw(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();
        $drawId = Draw::query()->where('organization_id', $owner->organization_id)->where('name', '12:00 md')->value('id');

        $matchingRequest = IntakeRequest::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'draw_id' => $drawId,
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => '12',
            'detected_amount' => 1000,
            'raw_text' => '1000 al 12 12 md',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);

        $otherRequest = IntakeRequest::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '7:00 pm')->value('id'),
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => '44',
            'detected_amount' => 500,
            'raw_text' => '500 al 44 7pm',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($owner)->get(route('intake-requests.index', ['draw_id' => $drawId]))
            ->assertOk()
            ->assertSeeText('12:00 md')
            ->assertDontSeeText('44');
    }

    public function test_draw_badge_appears_in_request_list(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();

        $this->actingAs($owner)->get(route('intake-requests.index'))
            ->assertOk()
            ->assertSee('inline-flex rounded-full bg-sky-100', false)
            ->assertSeeText('12:00 md');
    }

    public function test_rejected_request_requires_reason(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();

        $this->actingAs($owner)->post(route('intake-requests.reject', $request), [
            'rejection_reason' => '',
        ])->assertSessionHasErrors('rejection_reason');

        $this->actingAs($owner)->post(route('intake-requests.reject', $request), [
            'rejection_reason' => 'Customer asked to cancel.',
        ])->assertRedirect(route('intake-requests.index'));

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => IntakeRequest::STATUS_REJECTED,
            'rejected_by' => $owner->id,
            'notes' => 'Customer asked to cancel.',
        ]);

        $this->assertDatabaseHas('intake_request_events', [
            'intake_request_id' => $request->id,
            'event_type' => IntakeRequest::EVENT_REJECTED,
            'notes' => 'Customer asked to cancel.',
        ]);
    }

    public function test_seller_cannot_view_another_branch_request(): void
    {
        [$owner, $branchOne, $branchTwo, $request] = $this->makeOrganizationWithUsersAndRequest();

        $seller = User::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchOne->id,
            'role' => User::ROLE_SELLER,
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $otherBranchCustomer = Customer::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'name' => 'Other Customer',
            'phone' => '+50255500999',
        ]);

        $otherBranchMessage = IncomingMessage::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'customer_id' => $otherBranchCustomer->id,
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'from_identifier' => $otherBranchCustomer->phone,
            'to_identifier' => $branchTwo->channel_identifier,
            'raw_text' => '1000 al 28 2pm',
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        $otherBranchRequest = IntakeRequest::create([
            'organization_id' => $owner->organization_id,
            'branch_id' => $branchTwo->id,
            'draw_id' => Draw::query()->where('organization_id', $owner->organization_id)->where('name', '2:00 pm')->value('id'),
            'customer_id' => $otherBranchCustomer->id,
            'incoming_message_id' => $otherBranchMessage->id,
            'detected_number' => '28',
            'detected_amount' => 1000,
            'raw_text' => '1000 al 28 2pm',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($seller)->get(route('intake-requests.show', $otherBranchRequest))->assertForbidden();
    }

    private function makeOrganizationWithUsersAndRequest(string $status = IntakeRequest::STATUS_PENDING): array
    {
        $organization = Organization::create([
            'name' => 'Manage Org',
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

        $request = IntakeRequest::create([
            'organization_id' => $organization->id,
            'branch_id' => $branchTwo->id,
            'draw_id' => Draw::query()->where('organization_id', $organization->id)->where('name', '12:00 md')->value('id'),
            'customer_id' => null,
            'incoming_message_id' => null,
            'detected_number' => '28',
            'detected_amount' => 1000,
            'raw_text' => '1000 al 28 12 md',
            'status' => $status,
        ]);

        return [$owner->fresh(), $branchOne->fresh(), $branchTwo->fresh(), $request->fresh()];
    }
}

