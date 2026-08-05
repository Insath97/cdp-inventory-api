<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ActivityLogTrait;

class BranchRequest extends Model
{
    use SoftDeletes, ActivityLogTrait;

     protected $fillable = [
        'request_no',
        'branch_id',
        'requested_by',
        'request_date',
        'total_items',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'requested_products',
    ];

    protected $casts = [
        'requested_products' => 'array',
        'approved_at' => 'datetime',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('request_no', 'LIKE', "%{$search}%")
                ->orWhere('branch_id', 'LIKE', "%{$search}%")
                ->orWhere('requested_by', 'LIKE', "%{$search}%")
                ->orWhere('request_date', 'LIKE', "%{$search}%")
                ->orWhere('total_items', 'LIKE', "%{$search}%")
                ->orWhere('status', 'LIKE', "%{$search}%")
                ->orWhere('notes', 'LIKE', "%{$search}%")
                ->orWhere('approved_by', 'LIKE', "%{$search}%")
                ->orWhere('approved_at', 'LIKE', "%{$search}%")
                ->orWhere('requested_products', 'LIKE', "%{$search}%");
        });
    }

     // Branch this request belongs to
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    // User who created the request
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
    // User who approved the request (optional)
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}
