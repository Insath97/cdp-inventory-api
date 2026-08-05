<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ActivityLogTrait;

class CheckOut extends Model
{
    use SoftDeletes, ActivityLogTrait;

    protected $fillable = [
        'container_id',
        'branch_id',
        'product_id',
        'quantity',
        'checked_out_at',
        'status',
        'is_active',
        'description',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('container_id', 'LIKE', "%{$search}%")
                ->orWhere('product_id', 'LIKE', "%{$search}%")
                ->orWhere('quantity', 'LIKE', "%{$search}%")
                ->orWhere('checked_out_at', 'LIKE', "%{$search}%")
                ->orWhere('status', 'LIKE', "%{$search}%")
                ->orWhere('is_active', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
?>
