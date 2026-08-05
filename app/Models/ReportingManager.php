<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingManager extends Model
{
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'reporting_manager_id',
        'is_active',
        'can_login',
        'phone',
        'is_default',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_login' => 'boolean',
        'is_default' => 'boolean',
    ];

     public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('username', 'LIKE', "%{$search}%");
        });
    }
}
