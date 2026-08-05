<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'grn_number',
        'supplier_bank_account_id',
        'paid_by',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'status',
        'notes',
        'bill_image',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('payment_number', 'LIKE', "%{$search}%")
              ->orWhere('reference_number', 'LIKE', "%{$search}%")
              ->orWhere('grn_number', 'LIKE', "%{$search}%")
              ->orWhere('notes', 'LIKE', "%{$search}%")
              ->orWhereHas('supplier', function ($sq) use ($search) {
                  $sq->where('supplier_name', 'LIKE', "%{$search}%")
                     ->orWhere('company_name', 'LIKE', "%{$search}%");
              });
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplierBankAccount()
    {
        return $this->belongsTo(SupplierBankAccount::class, 'supplier_bank_account_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
