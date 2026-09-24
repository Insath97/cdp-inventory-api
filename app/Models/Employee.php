<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'employee_code',
        'email',
        'phone',
        'date_of_birth',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
    ];

    public function productAssignments()
    {
        return $this->hasMany(ProductAssignment::class, 'employee_id');
    }

    /**
     * Next free code in the EMP-0001 sequence (soft-deleted rows included,
     * so a deleted employee's code is never reused).
     */
    public static function generateCode(): string
    {
        $next = (static::withTrashed()->max('id') ?? 0) + 1;
        do {
            $code = 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::withTrashed()->where('employee_code', $code)->exists());

        return $code;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('full_name', 'LIKE', "%{$search}%")
                ->orWhere('employee_code', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }
}