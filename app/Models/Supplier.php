<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Supplier extends Model
{
    protected $fillable = [
        'supplier_name',
        'supplier_code',
        'company_name',
        'contact_person_name',
        'contact_person_phone',
        'alternate_phone',
        'address',
        'city',
        'state',
        'country',
        'phone',
        'whatsapp',
        'email',
        'website',
        'is_active',
        'description'
    ];

     protected $casts = [
        'is_active'=> 'boolean',
     ];

    public function scopeSearch($query, $search){
        return $query->where(function($q) use ($search){
            $q->where('supplier_name', 'LIKE', "%{$search}%")
            ->orWhere('supplier_code', 'LIKE', "%{$search}%")
            ->orWhere('company_name', 'LIKE', "%{$search}%")
            ->orWhere('contact_person_name', 'LIKE', "%{$search}%")
            ->orWhere('contact_person_phone', 'LIKE', "%{$search}%")
            ->orWhere('alternate_phone', 'LIKE', "%{$search}%")
            ->orWhere('address', 'LIKE', "%{$search}%")
            ->orWhere('city', 'LIKE', "%{$search}%")
            ->orWhere('state', 'LIKE', "%{$search}%")
            ->orWhere('country', 'LIKE', "%{$search}%")
            ->orWhere('phone', 'LIKE', "%{$search}%")
            ->orWhere('whatsapp', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%")
            ->orWhere('website', 'LIKE', "%{$search}%");
        });
    }


    public function bankAccounts()
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot(['unit_id', 'supply_quantity', 'unit_price', 'is_preferred'])
            ->withTimestamps();
    }

}
