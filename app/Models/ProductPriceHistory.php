<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'unit_price',
        'source_type',
        'source_id',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
