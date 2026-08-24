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
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            // The specific physical unit being transferred, when the product
            // is serial-tracked — same grn_item_serials link used by Product
            // Assignment. serial_number is a denormalized snapshot so this
            // row keeps reading correctly even if the serial record changes.
            $table->foreignId('grn_item_serial_id')->nullable()->constrained('grn_item_serials')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('quantity_requested', 15, 2)->default(0);
            $table->decimal('quantity_sent', 15, 2)->nullable();
            $table->decimal('quantity_received', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['stock_transfer_id']);
            $table->index(['product_id']);
            $table->index(['product_variant_id']);
            $table->index(['grn_item_serial_id']);
            $table->index(['unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
