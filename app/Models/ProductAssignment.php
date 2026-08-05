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
        'product_id',
        'product_sku',
        'product_name',
        'issue_date',
        'remarks',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
