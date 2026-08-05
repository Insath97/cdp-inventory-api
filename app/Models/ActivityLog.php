<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ActivityLog extends Model
{
     protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function scopeSearch($query, $search)
{
    return $query->where(function ($q) use ($search) {
        $q->where('description', 'LIKE', "%{$search}%")
            ->orWhere('action', 'LIKE', "%{$search}%")
            ->orWhere('module', 'LIKE', "%{$search}%")
            ->orWhereHas('user', function ($user) use ($search) {
                $user->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('email', 'LIKE', "%{$search}%");
            });
    });
}

    /**
     * Get the user who performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
