<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\DailyOrderClosure;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyOrderClosure>
 */
class DailyOrderClosureFactory extends Factory
{
    protected $model = DailyOrderClosure::class;

    public function definition(): array
    {
        $organization = Organization::query()->create([
            'name' => fake()->company(),
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => fake()->company() . ' Sucursal',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => fake()->unique()->bothify('branch-####'),
            'status' => Branch::STATUS_ACTIVE,
        ]);

        return [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'closure_date' => today()->toDateString(),
            'closed_by' => null,
            'pending_review_count' => 0,
            'confirmed_count' => 0,
            'preparing_count' => 0,
            'ready_for_dispatch_count' => 0,
            'dispatched_count' => 0,
            'cancelled_count' => 0,
            'rejected_count' => 0,
            'total_orders' => 0,
            'total_items' => 0,
            'total_order_value' => null,
            'notes' => fake()->optional()->sentence(),
            'export_path' => null,
            'exported_at' => null,
            'closed_at' => now(),
        ];
    }
}
