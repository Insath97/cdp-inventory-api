<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'brand_id',
        'main_category_id',
        'sub_category_id',
        'measurement_id',
        'unit_id',
        'container_id',
        'supplier_id',
        'product_code',
        'id_number',
        'product_name',
        'slug',
        'description',
        'is_variant',
        'is_active',
        'is_default',
        'product_type',
        'created_by',
        'is_pending_setup',
        'track_serial_numbers'
    ];

    protected $casts = [
        'is_variant' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_pending_setup' => 'boolean',
        'track_serial_numbers' => 'boolean'
    ];

    // Screens written before latest_purchase_price existed read
    // `purchase_price` — keep that name working everywhere the model
    // serializes to JSON, not just on hand-built responses.
    protected $appends = ['purchase_price'];

    public function getPurchasePriceAttribute()
    {
        return $this->latest_purchase_price;
    }

    protected static function booted()
    {
        static::addGlobalScope('department', function ($builder) {
            if (auth('api')->check()) {
                $user = auth('api')->user();
                if ($user && !$user->can('Product View All')) {
                    // Direct User hierarchy (parent_user_id) plus anyone who has this
                    // user set as their "Reporting Manager" in the Add/Edit User form
                    // (resolved through the reporting_managers directory).
                    $subordinateIds = User::where('parent_user_id', $user->id)
                        ->pluck('id')
                        ->merge($user->getReportingSubordinateIds())
                        ->push($user->id)
                        ->unique()
                        ->toArray();

                    $builder->where(function ($q) use ($subordinateIds) {
                        $q->whereIn('created_by', $subordinateIds)
                          ->orWhereNull('created_by');
                    });
                }
            }
        });
    }

     public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('product_name', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('product_code', 'LIKE', "%{$search}%")
                ->orWhere('id_number', 'LIKE', "%{$search}%");
        });
    }

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot(['unit_id', 'supply_quantity', 'unit_price', 'is_preferred'])
            ->withTimestamps();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(ProductPriceHistory::class)->orderByDesc('effective_date')->orderByDesc('id');
    }
}
