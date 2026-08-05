<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ActivityLogTrait;

class CheckIn extends Model
{
    use SoftDeletes, ActivityLogTrait;

    protected $fillable = [
        'check_in_no',
        'branch_id',
        'container_id',
        'product_id',
        'quantity',
        'date',
        'supplier_id',
        'ref_no',
        'status',
        'is_active',
        'description',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('check_in_no', 'LIKE', "%{$search}%")
                ->orWhere('branch_id', 'LIKE', "%{$search}%")
                ->orWhere('container_id', 'LIKE', "%{$search}%")
                ->orWhere('product_id', 'LIKE', "%{$search}%")
                ->orWhere('quantity', 'LIKE', "%{$search}%")
                ->orWhere('date', 'LIKE', "%{$search}%")
                ->orWhere('supplier_id', 'LIKE', "%{$search}%")
                ->orWhere('ref_no', 'LIKE', "%{$search}%")
                ->orWhere('status', 'LIKE', "%{$search}%")
                ->orWhere('is_active', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }   

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
?>
