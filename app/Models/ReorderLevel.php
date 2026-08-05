<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReorderLevel extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'branch_id',
        'min_quantity',
        'reorder_quantity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_quantity' => 'decimal:2',
        'reorder_quantity' => 'decimal:2',
    ];

    /**
     * Scope to search reorder levels.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('min_quantity', 'LIKE', "%{$search}%")
              ->orWhere('reorder_quantity', 'LIKE', "%{$search}%")
              ->orWhereHas('product', function ($pq) use ($search) {
                  $pq->where('product_name', 'LIKE', "%{$search}%")
                     ->orWhere('product_code', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('productVariant', function ($vq) use ($search) {
                  $vq->where('variant_name', 'LIKE', "%{$search}%")
                     ->orWhere('sku', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('branch', function ($bq) use ($search) {
                  $bq->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('branch_code', 'LIKE', "%{$search}%");
              });
        });
    }

    /**
     * Relationship to Product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship to ProductVariant.
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Relationship to Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
