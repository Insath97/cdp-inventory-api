<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBankAccount extends Model
{
    protected $fillable = [
        'supplier_id',
        'bank_name',
        'account_holder_name',
        'branch_name',
        'account_number',
        'is_default',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('bank_name', 'LIKE', "%{$search}%")
                ->orWhere('account_holder_name', 'LIKE', "%{$search}%")
                ->orWhere('branch_name', 'LIKE', "%{$search}%")
                ->orWhere('account_number', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }
}
