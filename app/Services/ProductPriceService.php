<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;

class ProductPriceService
{
    /**
     * Persist a new "current" purchase price for a product and append it to
     * the price-history audit trail. Never overwrites — every call inserts
     * a new history row.
     */
    public static function recordPrice(
        int $productId,
        float $unitPrice,
        string $sourceType,
        int $sourceId,
        ?string $date = null,
        ?int $createdBy = null,
    ): ProductPriceHistory {
        $history = ProductPriceHistory::create([
            'product_id'     => $productId,
            'unit_price'     => $unitPrice,
            'source_type'    => $sourceType,
            'source_id'      => $sourceId,
            'effective_date' => $date ?? now()->toDateString(),
            'created_by'     => $createdBy,
        ]);

        Product::whereKey($productId)->update(['latest_purchase_price' => $unitPrice]);

        return $history;
    }

    /**
     * The single place "current purchase price" is resolved for a product:
     * the persisted column when set, otherwise the same GRN/supplier_products
     * fallback chain previously duplicated across ProductController,
     * StockLedgerService and StockLedgerController.
     */
    public static function resolveCurrentPrice(int $productId): float
    {
        $persisted = Product::whereKey($productId)->value('latest_purchase_price');
        if ($persisted !== null) {
            return (float) $persisted;
        }

        $fromGrn = DB::table('grn_items')
            ->where('product_id', $productId)
            ->latest('id')
            ->value('unit_price');
        if ($fromGrn) {
            return (float) $fromGrn;
        }

        return (float) (DB::table('supplier_products')
            ->where('product_id', $productId)
            ->value('unit_price') ?? 0);
    }
}
