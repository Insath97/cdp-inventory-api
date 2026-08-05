<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnNoteItem extends Model
{
    protected $fillable = [
        'purchase_return_note_id',
        'grn_item_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'quantity_returned',
        'unit_price',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%");
        });
    }

    public function purchaseReturnNote()
    {
        return $this->belongsTo(PurchaseReturnNote::class);
    }

    public function grnItem()
    {
        return $this->belongsTo(GrnItem::class);
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
