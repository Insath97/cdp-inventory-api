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
        'discount_type',
        'discount_value',
        'expiry_date',
        'batch_number',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
    ];

    /** Line subtotal before any discount. */
    public function getSubtotalAttribute(): float
    {
        return (float) $this->quantity_received * (float) $this->unit_price;
    }

    /** The discount resolved to rupees, whichever way it was entered. */
    public function getDiscountAmountAttribute(): float
    {
        $subtotal = $this->subtotal;
        $value = (float) $this->discount_value;
        if ($value <= 0) {
            return 0.0;
        }

        $amount = $this->discount_type === 'amount' ? $value : $subtotal * $value / 100;

        return (float) min(max($amount, 0), $subtotal);
    }

    /** What this line actually costs after its discount. */
    public function getLineTotalAttribute(): float
    {
        return $this->subtotal - $this->discount_amount;
    }

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
