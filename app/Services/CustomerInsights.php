<?php

namespace App\Services;

use Carbon\CarbonInterface;

readonly class CustomerInsights
{
    /**
     * @param  array<int, array{product: string, times_ordered: int}>  $favorite_products
     * @param  array{name: string, percentage: float}  $favorite_channel
     * @param  array<int, string>  $active_days
     * @param  array<int, string>  $inactive_days
     */
    public function __construct(
        public string $segment,
        public array $favorite_products,
        public array $favorite_channel,
        public ?string $favorite_hour,
        public ?float $average_days,
        public array $inactive_days,
        public int $total_orders,
        public int $completed_orders,
        public int $cancelled_orders,
        public ?float $average_items,
        public ?float $average_ticket_items,
        public array $active_days,
        public ?CarbonInterface $first_order_date = null,
        public ?CarbonInterface $last_order_date = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'segment' => $this->segment,
            'favorite_products' => $this->favorite_products,
            'favorite_channel' => $this->favorite_channel,
            'favorite_hour' => $this->favorite_hour,
            'average_days' => $this->average_days,
            'inactive_days' => $this->inactive_days,
            'total_orders' => $this->total_orders,
            'completed_orders' => $this->completed_orders,
            'cancelled_orders' => $this->cancelled_orders,
            'average_items' => $this->average_items,
            'average_ticket_items' => $this->average_ticket_items,
            'active_days' => $this->active_days,
            'first_order_date' => $this->first_order_date,
            'last_order_date' => $this->last_order_date,
        ];
    }
}
