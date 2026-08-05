<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    protected $table = 'stock_ledger';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'branch_id',
        'reference_type',
        'reference_id',
        'transaction_date',
        'quantity_in',
        'quantity_out',
        'balance',
        'unit_price',
        'total_amount',
        'unit_id',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:4',
        'quantity_out' => 'decimal:4',
        'balance' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
