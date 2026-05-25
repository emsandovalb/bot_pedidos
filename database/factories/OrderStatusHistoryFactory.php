<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => Order::STATUS_PENDING_REVIEW,
            'changed_by_user_id' => null,
            'changed_via' => 'system',
            'reason' => null,
            'metadata_json' => [],
            'created_at' => now(),
        ];
    }
}
