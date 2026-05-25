<?php

namespace Database\Factories;

use App\Models\ManualReview;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualReview>
 */
class ManualReviewFactory extends Factory
{
    protected $model = ManualReview::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'reviewed_by_user_id' => null,
            'decision' => 'approved',
            'reason' => null,
            'before_json' => [],
            'after_json' => [],
            'suggested_changes_json' => [],
            'reviewed_at' => now(),
        ];
    }
}
