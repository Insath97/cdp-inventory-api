<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnNote extends Model
{
    protected $fillable = [
        'grn_id',
        'supplier_id',
        'branch_id',
        'created_by',
        'prn_number',
        'return_date',
        'reason',
        'status',
    ];

    protected $casts = [
        'return_date' => 'date:Y-m-d',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('prn_number', 'LIKE', "%{$search}%")
              ->orWhere('reason', 'LIKE', "%{$search}%")
              ->orWhere('status', 'LIKE', "%{$search}%");
        });
    }

    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnNoteItem::class, 'purchase_return_note_id');
    }
}
