<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('system_quantity', 15, 2)->default(0);
            $table->decimal('physical_quantity', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['stock_take_id']);
            $table->index(['product_id']);
            $table->index(['product_variant_id']);
            $table->index(['unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
