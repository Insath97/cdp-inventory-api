<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Models\Unit;

class SupplierProductService
{
    /**
     * Ensure a supplier_products link exists for the given supplier/product pair,
     * auto-resolving a default unit_id so the caller never has to supply one.
     */
    public function ensureLinked(int $supplierId, int $productId, array $overrides = []): SupplierProduct
    {
        return SupplierProduct::firstOrCreate(
            ['supplier_id' => $supplierId, 'product_id' => $productId],
            [
                'unit_id' => $overrides['unit_id'] ?? $this->resolveDefaultUnitId($productId),
                'supply_quantity' => $overrides['supply_quantity'] ?? 1,
                'unit_price' => $overrides['unit_price'] ?? null,
                'is_preferred' => $overrides['is_preferred'] ?? false,
                'is_active' => true,
            ]
        );
    }

    protected function resolveDefaultUnitId(int $productId): ?int
    {
        $productUnitId = Product::whereKey($productId)->value('unit_id');
        if ($productUnitId) {
            return $productUnitId;
        }

        return Unit::where('is_base_unit', true)->value('id') ?? Unit::value('id');
    }
}
