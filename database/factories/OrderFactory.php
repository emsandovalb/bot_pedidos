<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

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

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'external_id' => fake()->optional()->uuid(),
        ]);

        return [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'incoming_message_id' => null,
            'source_channel' => 'telegram',
            'external_message_id' => fake()->optional()->uuid(),
            'status' => Order::STATUS_PENDING_REVIEW,
            'parser_confidence' => fake()->randomFloat(2, 0, 1),
            'raw_message_text' => fake()->sentence(),
            'parsed_payload_json' => [
                'items' => [],
            ],
            'notes' => fake()->optional()->sentence(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'preparing_at' => null,
            'ready_for_dispatch_at' => null,
            'dispatched_at' => null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ];
    }
}
