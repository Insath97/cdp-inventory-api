<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'unit_id',
        'supply_quantity',
        'unit_price',
        'is_preferred',
        'is_active',
    ];

    protected $casts = [
        'supply_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('supplier', function ($sq) use ($search) {
                $sq->where('supplier_name', 'LIKE', "%{$search}%")
                   ->orWhere('company_name', 'LIKE', "%{$search}%");
            })->orWhereHas('product', function ($pq) use ($search) {
                $pq->where('product_name', 'LIKE', "%{$search}%");
            });
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'supplier_product_id');
    }

    public function mainCategory()
    {
        return $this->belongsTo(MainCategory::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }
}
