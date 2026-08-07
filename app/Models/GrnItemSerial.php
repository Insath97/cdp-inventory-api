<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrnItemSerial extends Model
{
    protected $fillable = [
        'grn_item_id',
        'product_id',
        'product_variant_id',
        'serial_number',
    ];

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
}
