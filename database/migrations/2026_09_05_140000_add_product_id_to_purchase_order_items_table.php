<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('purchase_order_id')->constrained('products')->cascadeOnDelete();
        });

        // Backfill product_id from existing variant relationships
        DB::statement("
            UPDATE purchase_order_items poi
            JOIN product_variants pv ON poi.variant_id = pv.id
            SET poi.product_id = pv.product_id
            WHERE poi.product_id IS NULL
        ");

        // Make variant_id nullable
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->unsignedBigInteger('variant_id')->nullable(false)->change();
        });
    }
};
