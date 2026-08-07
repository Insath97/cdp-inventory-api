<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    protected $fillable = [
        'grn_id',
        'purchase_order_item_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'container_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'expiry_date',
        'batch_number',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('batch_number', 'LIKE', "%{$search}%");
        });
    }

    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function serials()
    {
        return $this->hasMany(GrnItemSerial::class);
    }
}
