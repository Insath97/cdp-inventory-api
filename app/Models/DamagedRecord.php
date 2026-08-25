<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamagedRecord extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'serial_number',
        'branch_id',
        'reported_by',
        'approved_by',
        'damage_number',
        'damage_date',
        'quantity',
        'reason',
        'status',
        'is_active',
    ];

    protected $casts = [
        'damage_date' => 'date',
        'quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('damage_number', 'LIKE', "%$search%")
                ->orWhere('reason', 'LIKE', "%$search%");
        });
    }   

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

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockLedgerEntries()
    {
        return $this->morphMany(StockLedger::class, 'reference');
    }
}
