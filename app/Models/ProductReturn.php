<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_returns';

    protected $fillable = [
        'return_code',
        'person_name',
        'group_name',
        'branch_name',
        'department_name',
        'products',
        'return_date',
        'remarks'
    ];

    protected $casts = [
        'products' => 'array',
        'return_date' => 'date:Y-m-d'
    ];


    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('return_code', 'LIKE', "%{$search}%")
                ->orWhere('person_name', 'LIKE', "%{$search}%")
                ->orWhere('group_name', 'LIKE', "%{$search}%")
                ->orWhere('branch_name', 'LIKE', "%{$search}%")
                ->orWhere('department_name', 'LIKE', "%{$search}%")
                ->orWhere('return_date', 'LIKE', "%{$search}%")
                ->orWhere('remarks', 'LIKE', "%{$search}%");
        });
    }
}
