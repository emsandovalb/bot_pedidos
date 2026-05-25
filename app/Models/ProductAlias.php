<?php

namespace App\Models;

use App\Services\ProductTextNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'product_id',
        'alias',
        'normalized_alias',
        'match_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'match_weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductAlias $alias): void {
            $alias->normalized_alias = app(ProductTextNormalizer::class)->normalize($alias->alias);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
