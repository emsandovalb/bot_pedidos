<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\MessageResponse;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimulatorIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_request_creates_one_pending_request(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();
        $drawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '2:00 pm')->value('id');

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510001',
            'customer_name' => 'Mario Gomez',
            'raw_message' => '1000 al 28 2pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseCount('intake_request_events', 1);

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'from_identifier' => '+50255510001',
            'to_identifier' => $branch->channel_identifier,
            'raw_text' => '1000 al 28 2pm',
            'status' => IncomingMessage::STATUS_RECEIVED,
        ]);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 28 2pm',
            'status' => IntakeRequest::STATUS_PENDING,
            'draw_id' => $drawId,
            'detected_amount' => 1000,
            'detected_number' => '28',
            'notes' => null,
        ]);

        $this->assertDatabaseHas('message_responses', [
            'response_type' => 'confirmation',
        ]);
    }

    public function test_same_amount_multiple_numbers_creates_two_needs_review_requests(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();
        $drawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '5:00 pm')->value('id');

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510002',
            'customer_name' => 'Maria Lopez',
            'raw_message' => '1000 al 25 y 28 5pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 2);
        $this->assertDatabaseCount('intake_request_events', 2);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 5pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_amount' => 1000,
            'detected_number' => '25',
            'notes' => 'Multiple numbers detected for the same amount. Manual review required.',
        ]);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 5pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_amount' => 1000,
            'detected_number' => '28',
        ]);
    }

    public function test_multiple_request_patterns_create_two_needs_review_requests(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();
        $drawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '2:00 pm')->value('id');

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510003',
            'customer_name' => 'Jose Ramirez',
            'raw_message' => '1000 al 28 y 2000 al 45 2pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 2);
        $this->assertDatabaseCount('intake_request_events', 2);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 28 y 2000 al 45 2pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_amount' => 1000,
            'detected_number' => '28',
            'notes' => 'Multiple request patterns detected. Manual review required.',
        ]);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 28 y 2000 al 45 2pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_amount' => 2000,
            'detected_number' => '45',
        ]);
    }

    public function test_invalid_message_creates_one_needs_review_request_with_raw_text_preserved(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510004',
            'customer_name' => null,
            'raw_message' => 'hola',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseCount('intake_request_events', 1);

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => 'hola',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'detected_amount' => null,
            'detected_number' => null,
            'notes' => 'Could not detect a valid amount/number pattern.',
        ]);
    }

    public function test_generated_confirmation_text_includes_draw_label(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510005',
            'customer_name' => 'Ana Ruiz',
            'raw_message' => '1000 al 25 y 28 7pm',
        ])->assertRedirect(route('simulator.index'));

        $result = session('simulator_result');

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 2);
        $this->assertSame(2, $result['created_requests_count']);
        $this->assertStringContainsString('Sorteo 7:00 pm', $result['customer_confirmation_text']);
        $this->assertSame('confirmation', MessageResponse::query()->firstOrFail()->response_type);
    }

    public function test_missing_draw_creates_needs_review_request(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510007',
            'customer_name' => 'No Draw',
            'raw_message' => '1000 al 28',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 28',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => null,
            'detected_amount' => 1000,
            'detected_number' => '28',
            'notes' => 'Draw schedule is required. Manual review required.',
        ]);
    }

    public function test_draw_assignment_works_for_two_pm_and_twelve_md(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();
        $twoPmDrawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '2:00 pm')->value('id');
        $twelveMdDrawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '12:00 md')->value('id');

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510009',
            'customer_name' => 'Two PM',
            'raw_message' => '2500 al 67 2pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '2500 al 67 2pm',
            'status' => IntakeRequest::STATUS_PENDING,
            'draw_id' => $twoPmDrawId,
            'detected_amount' => 2500,
            'detected_number' => '67',
        ]);

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510010',
            'customer_name' => 'Twelve MD',
            'raw_message' => '5000 al 10 12md',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '5000 al 10 12md',
            'status' => IntakeRequest::STATUS_PENDING,
            'draw_id' => $twelveMdDrawId,
            'detected_amount' => 5000,
            'detected_number' => '10',
        ]);
    }

    public function test_multiple_numbers_share_the_same_detected_draw(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();
        $drawId = Draw::query()->where('organization_id', $user->organization_id)->where('name', '12:00 md')->value('id');

        $this->actingAs($user)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510011',
            'customer_name' => 'Shared Draw',
            'raw_message' => '1000 al 25 y 28 para las 12',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseCount('requests', 2);
        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 para las 12',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_number' => '25',
        ]);
        $this->assertDatabaseHas('requests', [
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 para las 12',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'draw_id' => $drawId,
            'detected_number' => '28',
        ]);
    }

    public function test_customer_is_reused_by_phone(): void
    {
        [$user, $branch] = $this->makeOrgWithBranchAndOwner();

        $payload = [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255510006',
            'customer_name' => 'Carla Perez',
            'raw_message' => '1000 al 28 2pm',
        ];

        $this->actingAs($user)->post(route('simulator.store'), $payload)->assertRedirect(route('simulator.index'));

        $this->actingAs($user)->post(route('simulator.store'), [
            ...$payload,
            'raw_message' => '500 numero 99 5pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertSame(1, Customer::query()
            ->where('organization_id', $user->organization_id)
            ->where('phone', '+50255510006')
            ->count());

        $this->assertSame(2, IncomingMessage::query()
            ->where('organization_id', $user->organization_id)
            ->where('from_identifier', '+50255510006')
            ->count());
    }

    private function makeOrgWithBranchAndOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255500011',
            'status' => Branch::STATUS_ACTIVE,
        ]);

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

        return [$user->fresh(), $branch->fresh()];
    }
}
