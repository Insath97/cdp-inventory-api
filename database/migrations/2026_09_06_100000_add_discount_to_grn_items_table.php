<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line discount on a GRN item. The discount is entered either as a
 * percentage of the line subtotal or as a flat rupee amount, so the type is
 * stored alongside the value and the line total is derived as:
 *
 *   subtotal = quantity_received * unit_price
 *   discount = type === 'percentage' ? subtotal * value / 100 : value
 *   total    = subtotal - discount
 *
 * Purely additive with safe defaults — existing rows read back as "no
 * discount" and every existing total is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            if (! Schema::hasColumn('grn_items', 'discount_type')) {
                $table->string('discount_type', 20)->default('percentage')->after('unit_price');
            }
            if (! Schema::hasColumn('grn_items', 'discount_value')) {
                $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
