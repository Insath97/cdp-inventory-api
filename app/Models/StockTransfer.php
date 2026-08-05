<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_type',
        'from_branch_id',
        'to_branch_id',
        'from_employee_id',
        'to_employee_id',
        'requested_by',
        'approved_by',
        'transfer_number',
        'transfer_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date:Y-m-d',
    ];

    // ── Transfer types ────────────────────────────────────────────
    const TRANSFER_TYPES = [
        'branch_to_branch',
        'employee_to_employee',
        'branch_to_employee',
        'employee_to_branch',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('transfer_number', 'LIKE', "%{$search}%")
                ->orWhere('status', 'LIKE', "%{$search}%")
                ->orWhere('transfer_type', 'LIKE', "%{$search}%")
                ->orWhere('notes', 'LIKE', "%{$search}%");
        });
    }

    // ── Relationships ─────────────────────────────────────────────

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** Source employee (mapped to users table) */
    public function fromEmployee()
    {
        return $this->belongsTo(User::class, 'from_employee_id');
    }

    /** Destination employee (mapped to users table) */
    public function toEmployee()
    {
        return $this->belongsTo(User::class, 'to_employee_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Whether this transfer type involves a source branch */
    public function hasFromBranch(): bool
    {
        return in_array($this->transfer_type, ['branch_to_branch', 'branch_to_employee']);
    }

    /** Whether this transfer type involves a destination branch */
    public function hasToBranch(): bool
    {
        return in_array($this->transfer_type, ['branch_to_branch', 'employee_to_branch']);
    }

    /** Whether this transfer type involves a source employee */
    public function hasFromEmployee(): bool
    {
        return in_array($this->transfer_type, ['employee_to_employee', 'employee_to_branch']);
    }

    /** Whether this transfer type involves a destination employee */
    public function hasToEmployee(): bool
    {
        return in_array($this->transfer_type, ['employee_to_employee', 'branch_to_employee']);
    }
}
