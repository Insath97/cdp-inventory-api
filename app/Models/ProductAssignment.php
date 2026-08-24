<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_assignments';

    protected $fillable = [
        'assignment_code',
        'person_name',
        'group_name',
        'branch_name',
        'department_name',
        'product_variant_id',
        'grn_item_serial_id',
        'serial_number',
        'user_id',
        'branch_id',
        'product_id',
        'product_sku',
        'product_name',
        'quantity',
        'issue_date',
        'remarks',
        'is_active',
        'returned_at',
        'returned_by',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function grnItemSerial()
    {
        return $this->belongsTo(GrnItemSerial::class, 'grn_item_serial_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBranch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
