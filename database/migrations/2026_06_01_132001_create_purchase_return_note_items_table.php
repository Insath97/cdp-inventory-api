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
        Schema::create('purchase_return_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_note_id')->constrained('purchase_return_notes')->cascadeOnDelete();
            $table->foreignId('grn_item_id')->constrained('grn_items');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('quantity_returned', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_note_items');
    }
};
