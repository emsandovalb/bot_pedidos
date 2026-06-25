<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrderIngestionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_fingerprint_for_new_order(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');

        $this->assertNotEmpty($order->order_fingerprint);
        $this->assertNotNull($order->duplicate_checked_at);
    }

    public function test_first_order_is_not_marked_as_duplicate(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $order = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');

        $this->assertNull($order->possible_duplicate_of_order_id);
        $this->assertNull($order->duplicate_score);
        $this->assertNull($order->duplicate_reason);
    }

    public function test_second_same_customer_same_items_order_within_window_is_possible_duplicate(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $firstOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:10:00');

        $this->assertSame($firstOrder->id, $secondOrder->possible_duplicate_of_order_id);
        $this->assertSame(95.0, (float) $secondOrder->duplicate_score);
        $this->assertNotEmpty($secondOrder->duplicate_reason);
        $this->assertNotNull($secondOrder->duplicate_checked_at);
    }

    public function test_same_items_from_different_customer_are_not_marked_as_duplicate(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();
        $otherCustomer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Other Customer',
            'phone' => '+50255550099',
            'external_id' => null,
        ]);

        $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $otherCustomer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:10:00');

        $this->assertNull($secondOrder->possible_duplicate_of_order_id);
        $this->assertNull($secondOrder->duplicate_score);
        $this->assertNull($secondOrder->duplicate_reason);
        $this->assertNotNull($secondOrder->duplicate_checked_at);
    }

    public function test_same_customer_after_time_window_is_not_marked_as_duplicate(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $firstOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:40:00');

        $this->assertNull($secondOrder->possible_duplicate_of_order_id);
        $this->assertNull($secondOrder->duplicate_score);
        $this->assertNull($secondOrder->duplicate_reason);
        $this->assertNotSame($firstOrder->id, $secondOrder->id);
    }

    public function test_duplicate_metadata_is_stored_on_duplicate_orders(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:10:00');

        $this->assertNotNull($secondOrder->duplicate_score);
        $this->assertNotEmpty($secondOrder->duplicate_reason);
        $this->assertNotNull($secondOrder->duplicate_checked_at);
        $this->assertNotEmpty($secondOrder->order_fingerprint);
    }

    public function test_order_detail_shows_duplicate_warning(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $firstOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:10:00');

        $this->actingAs($this->makeOwner($organization))
            ->get(route('orders.show', $secondOrder))
            ->assertOk()
            ->assertSee('Posible pedido duplicado')
            ->assertSee('pedido #' . $firstOrder->id)
            ->assertSee('Score')
            ->assertSee('Ver pedido original');
    }

    public function test_order_review_queue_shows_duplicate_badge(): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();

        $firstOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'telegram', 'msg-1', '2026-06-24 12:00:00');
        $secondOrder = $this->ingest($organization, $branch, $customer, '2 bolsas de jardin', 'whatsapp', 'msg-2', '2026-06-24 12:10:00');

        $this->actingAs($this->makeOwner($organization))
            ->get(route('order-reviews.index'))
            ->assertOk()
            ->assertSee('Posible duplicado')
            ->assertSee('Similar al pedido #' . $firstOrder->id)
            ->assertSee((string) $secondOrder->id);
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: Customer}
     */
    private function makeOrganizationBranchCustomer(): array
    {
        $organization = Organization::create([
            'name' => 'Duplicate Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@duplicate',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Customer One',
            'phone' => '+50255550001',
            'external_id' => null,
        ]);

        return [$organization, $branch, $customer];
    }

    private function makeOwner(Organization $organization)
    {
        return User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }

    private function ingest(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $rawText,
        string $sourceChannel,
        string $externalMessageId,
        string $now,
    ): Order {
        Carbon::setTestNow($now);

        try {
            return app(OrderIngestionService::class)->ingest(
                organization: $organization,
                branch: $branch,
                customer: $customer,
                rawMessageText: $rawText,
                sourceChannel: $sourceChannel,
                externalMessageId: $externalMessageId,
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
