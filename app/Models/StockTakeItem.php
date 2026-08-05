<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTakeItem extends Model
{
    protected $fillable = [
        'stock_take_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'system_quantity',
        'physical_quantity',
        'variance',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'physical_quantity' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->variance = $item->physical_quantity - $item->system_quantity;
        });
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('system_quantity', 'LIKE', "%{$search}%")
                ->orWhere('physical_quantity', 'LIKE', "%{$search}%")
                ->orWhere('variance', 'LIKE', "%{$search}%");
        });
    }

    public function stockTake()
    {
        return $this->belongsTo(StockTake::class, 'stock_take_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
