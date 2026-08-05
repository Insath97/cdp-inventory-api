<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time backfill: products.latest_purchase_price is a brand new
     * column, so every existing product would otherwise show a blank price
     * until its next GRN. Populate it from the same GRN/supplier_products
     * chain ProductPriceService uses for new products.
     */
    public function up(): void
    {
        $productIds = DB::table('products')->whereNull('latest_purchase_price')->pluck('id');

        foreach ($productIds as $productId) {
            $price = DB::table('grn_items')
                ->where('product_id', $productId)
                ->latest('id')
                ->value('unit_price');

            if ($price === null) {
                $price = DB::table('supplier_products')
                    ->where('product_id', $productId)
                    ->value('unit_price');
            }

            if ($price !== null) {
                DB::table('products')->where('id', $productId)->update(['latest_purchase_price' => $price]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill is one-directional — nothing to reverse (the column
        // itself is dropped by the migration that added it).
    }
};
