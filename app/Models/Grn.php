<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grn extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'branch_id',
        'received_by',
        'grn_number',
        'batch_number',
        'received_date',
        'notes',
        'supplier_bill_number',
        'bill_image',
    ];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('grn_number', 'LIKE', "%{$search}%")
              ->orWhere('batch_number', 'LIKE', "%{$search}%")
              ->orWhere('supplier_bill_number', 'LIKE', "%{$search}%")
              ->orWhere('notes', 'LIKE', "%{$search}%")
              ->orWhereHas('supplier', function ($sq) use ($search) {
                  $sq->where('supplier_name', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('items.product', function ($pq) use ($search) {
                  $pq->where('product_name', 'LIKE', "%{$search}%")
                     ->orWhere('product_code', 'LIKE', "%{$search}%");
              });
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }
}
