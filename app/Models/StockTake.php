<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTake extends Model
{
    protected $fillable = [
        'branch_id',
        'created_by',
        'approved_by',
        'take_number',
        'take_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'take_date' => 'date:Y-m-d',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('take_number', 'LIKE', "%{$search}%")
                ->orWhere('status', 'LIKE', "%{$search}%")
                ->orWhere('notes', 'LIKE', "%{$search}%");
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockTakeItem::class, 'stock_take_id');
    }
}
