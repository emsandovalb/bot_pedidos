<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'raw_text',
        'matched_text',
        'confidence_score',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Recalcula line_total = quantity * unit_price. Null si no hay precio unitario.
     */
    public function recalculateLineTotal(): void
    {
        $this->line_total = $this->unit_price === null
            ? null
            : round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
