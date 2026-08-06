<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_name',
        'supplier_id',
        'sku',
        'code',
        'barcode',
        'image',
        'color',
        'size',
        'material',
        'style',
        'description',
        'is_default',
        'is_active',
        'brand_id',
        'main_category_id',
        'sub_category_id',
        'measurement_id',
        'unit_id',
        'container_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to search variant fields.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sku', 'LIKE', "%{$search}%")
                ->orWhere('variant_name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%")
                ->orWhere('barcode', 'LIKE', "%{$search}%")
                ->orWhere('color', 'LIKE', "%{$search}%")
                ->orWhere('size', 'LIKE', "%{$search}%")
                ->orWhere('material', 'LIKE', "%{$search}%")
                ->orWhere('style', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Relationship back to the Product model.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Per-variant overrides for classification fields that otherwise live on
     * the parent Product. Null on a variant means "inherit from product" —
     * only forked variants (created via the Edit-never-mutates-the-original
     * flow) have these populated.
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function mainCategory()
    {
        return $this->belongsTo(MainCategory::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function measurement()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }
}
