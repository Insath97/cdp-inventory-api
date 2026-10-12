<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The variant layer is being retired: SKU and barcode become product-level
 * fields. Existing values are backfilled from each product's default (or
 * first) variant; product_variants stays in place read-only so historical
 * documents that reference a variant id keep resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('product_code');
            $table->string('barcode')->nullable()->unique()->after('sku');
        });

        // Backfill from the default variant (falling back to the first one).
        $variants = DB::table('product_variants')
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->unique('product_id');

        foreach ($variants as $variant) {
            DB::table('products')
                ->where('id', $variant->product_id)
                ->whereNull('sku')
                ->update([
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['sku', 'barcode']);
        });
    }
};
