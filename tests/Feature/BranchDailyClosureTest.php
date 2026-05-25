<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchDailyClosure;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchDailyClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_closure_creation_snapshots_totals(): void
    {
        [$organization, $owner, $branchOne, $branchTwo] = $this->makeOrganizationFixture();

        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_PENDING, null, null, 1000, '1000 al 28');
        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_PENDING, null, null, 500, '500 numero 45');
        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_CONFIRMED, $owner->id, now(), 1500.50, '1500 al 12');
        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_REJECTED, $owner->id, now(), 200, '200 al 99');

        $this->actingAs($owner)->post(route('closures.store'), [
            'branch_id' => $branchOne->id,
            'closure_date' => today()->toDateString(),
            'notes' => 'End of day snapshot.',
        ])->assertRedirect(route('closures.index'));

        $this->assertDatabaseHas('branch_daily_closures', [
            'organization_id' => $organization->id,
            'branch_id' => $branchOne->id,
            'closed_by' => $owner->id,
            'closure_date' => today()->startOfDay()->toDateTimeString(),
            'total_requests' => 4,
            'total_confirmed' => 1,
            'total_rejected' => 1,
            'total_pending' => 2,
            'total_amount_confirmed' => 1500.5,
        ]);
    }

    public function test_duplicate_closure_is_prevented(): void
    {
        [, $owner, $branchOne] = $this->makeOrganizationFixture();

        $this->actingAs($owner)->post(route('closures.store'), [
            'branch_id' => $branchOne->id,
            'closure_date' => today()->toDateString(),
        ])->assertRedirect(route('closures.index'));

        $this->actingAs($owner)->post(route('closures.store'), [
            'branch_id' => $branchOne->id,
            'closure_date' => today()->toDateString(),
        ])->assertSessionHasErrors('closure_date');

        $this->assertSame(1, BranchDailyClosure::query()->count());
    }

    public function test_seller_can_close_only_assigned_branch(): void
    {
        [$organization, $owner, $branchOne, $branchTwo, $seller] = $this->makeOrganizationFixture(withSeller: true);

        $this->actingAs($seller)->post(route('closures.store'), [
            'branch_id' => $branchTwo->id,
            'closure_date' => today()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($seller)->post(route('closures.store'), [
            'branch_id' => $branchOne->id,
            'closure_date' => today()->toDateString(),
        ])->assertRedirect(route('closures.index'));

        $this->assertDatabaseHas('branch_daily_closures', [
            'organization_id' => $organization->id,
            'branch_id' => $branchOne->id,
            'closed_by' => $seller->id,
        ]);
    }

    public function test_dashboard_reporting_visibility_matches_scope(): void
    {
        [$organization, $owner, $branchOne, $branchTwo, $seller] = $this->makeOrganizationFixture(withSeller: true);

        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_PENDING, null, null, 100, '100 al 10');
        $this->makeRequest($organization->id, $branchTwo->id, IntakeRequest::STATUS_PENDING, null, null, 200, '200 al 20');

        $ownerResponse = $this->actingAs($owner)->get(route('dashboard'));
        $ownerResponse->assertOk();
        $ownerResponse->assertSeeText('Branch One');
        $ownerResponse->assertSeeText('Branch Two');

        $sellerResponse = $this->actingAs($seller)->get(route('dashboard'));
        $sellerResponse->assertOk();
        $sellerResponse->assertSeeText('Branch One');
        $sellerResponse->assertDontSeeText('Branch Two');
    }

    public function test_authorized_user_can_view_closure_detail(): void
    {
        [$organization, $owner, $branchOne] = $this->makeOrganizationFixture();
        $request = $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_CONFIRMED, $owner->id, now(), 1000, '1000 al 28', 'Maria Lopez', '+50255512345');

        $closure = $this->createClosure($owner, $branchOne, today()->toDateString());

        $response = $this->actingAs($owner)->get(route('closures.show', $closure));

        $response->assertOk();
        $response->assertSeeText('Detalle de cierre');
        $response->assertSeeText('Organización');
        $response->assertSeeText('Maria Lopez');
        $response->assertSeeText('+50255512345');
        $response->assertSeeText((string) $request->id);
    }

    public function test_seller_cannot_view_another_branch_closure(): void
    {
        [$organization, $owner, $branchOne, $branchTwo, $seller] = $this->makeOrganizationFixture(withSeller: true);

        $this->makeRequest($organization->id, $branchTwo->id, IntakeRequest::STATUS_PENDING, null, null, 500, '500 al 11', 'Juan Perez', '+50255599999');
        $closure = $this->createClosure($owner, $branchTwo, today()->toDateString());

        $this->actingAs($seller)->get(route('closures.show', $closure))->assertForbidden();
    }

    public function test_csv_export_returns_expected_content(): void
    {
        [$organization, $owner, $branchOne] = $this->makeOrganizationFixture();
        $request = $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_CONFIRMED, $owner->id, now(), 1500, '1500 al 28', 'Luis Gomez', '+50255577777');

        $closure = $this->createClosure($owner, $branchOne, today()->toDateString());

        $response = $this->actingAs($owner)->get(route('closures.export', $closure));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('organization_name,branch_name,closure_date,closed_by,closed_at', $csv);
        $this->assertStringContainsString('Ops Org', $csv);
        $this->assertStringContainsString('Branch One', $csv);
        $this->assertStringContainsString('Luis Gomez', $csv);
        $this->assertStringContainsString((string) $request->id, $csv);
    }

    public function test_viewer_can_export_but_cannot_close_day(): void
    {
        [$organization, $owner, $branchOne, $branchTwo, $seller] = $this->makeOrganizationFixture(withSeller: true);
        $viewer = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_VIEWER,
            'name' => 'Viewer',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $this->makeRequest($organization->id, $branchOne->id, IntakeRequest::STATUS_PENDING, null, null, 100, '100 al 10', 'Ana Ruiz', '+50255588888');
        $closure = $this->createClosure($owner, $branchOne, today()->toDateString());

        $this->actingAs($viewer)->get(route('closures.export', $closure))->assertOk();
        $this->actingAs($viewer)->post(route('closures.store'), [
            'branch_id' => $branchOne->id,
            'closure_date' => today()->toDateString(),
        ])->assertForbidden();
    }

    private function makeOrganizationFixture(bool $withSeller = false): array
    {
        $organization = Organization::create([
            'name' => 'Ops Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branchOne = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch One',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501001',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $branchTwo = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Branch Two',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255501002',
            'status' => Branch::STATUS_ACTIVE,
        ]);

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

        $seller = null;
        if ($withSeller) {
            $seller = User::create([
                'organization_id' => $organization->id,
                'branch_id' => $branchOne->id,
                'role' => User::ROLE_SELLER,
                'name' => 'Seller',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
        }

        return $withSeller
            ? [$organization->fresh(), $owner->fresh(), $branchOne->fresh(), $branchTwo->fresh(), $seller->fresh()]
            : [$organization->fresh(), $owner->fresh(), $branchOne->fresh(), $branchTwo->fresh()];
    }

    private function makeRequest(
        int $organizationId,
        int $branchId,
        string $status,
        ?int $confirmedBy,
        ?\Illuminate\Support\Carbon $timestamp,
        int|float $amount,
        string $rawText,
        ?string $customerName = null,
        ?string $customerPhone = null,
    ): IntakeRequest {
        $customer = null;
        if ($customerName || $customerPhone) {
            $customer = Customer::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'name' => $customerName,
                'phone' => $customerPhone ?? fake()->unique()->phoneNumber(),
            ]);
        }

        $incomingMessage = null;
        if ($customer) {
            $incomingMessage = IncomingMessage::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
                'from_identifier' => $customer->phone,
                'to_identifier' => '+50255501000',
                'raw_text' => $rawText,
                'status' => IncomingMessage::STATUS_RECEIVED,
                'received_at' => $timestamp ?? now(),
            ]);
        }

        $request = IntakeRequest::create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'customer_id' => $customer?->id,
            'incoming_message_id' => $incomingMessage?->id,
            'detected_number' => '28',
            'detected_amount' => $amount,
            'raw_text' => $rawText,
            'status' => $status,
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => $status === IntakeRequest::STATUS_CONFIRMED ? ($timestamp ?? now()) : null,
            'rejected_by' => $status === IntakeRequest::STATUS_REJECTED ? $confirmedBy : null,
            'rejected_at' => $status === IntakeRequest::STATUS_REJECTED ? ($timestamp ?? now()) : null,
            'notes' => null,
        ]);

        if ($timestamp) {
            $request->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->saveQuietly();
        }

        return $request->fresh();
    }

    private function createClosure(User $user, Branch $branch, string $closureDate): BranchDailyClosure
    {
        return BranchDailyClosure::create([
            'organization_id' => $user->organization_id,
            'branch_id' => $branch->id,
            'closed_by' => $user->id,
            'closure_date' => $closureDate,
            'total_requests' => 1,
            'total_confirmed' => 1,
            'total_rejected' => 0,
            'total_pending' => 0,
            'total_amount_confirmed' => 1000,
            'notes' => 'Test closure',
            'closed_at' => now(),
        ]);
    }
}
