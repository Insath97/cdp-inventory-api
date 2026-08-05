<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiryRecord extends Model
{
    protected $fillable = [
        'grn_item_id',
        'product_id',
        'product_variant_id',
        'branch_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'status',
        'is_active',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to search expiry records.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('batch_number', 'LIKE', "%{$search}%")
              ->orWhere('status', 'LIKE', "%{$search}%")
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
     * Relationship to GrnItem.
     */
    public function grnItem()
    {
        return $this->belongsTo(GrnItem::class);
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
