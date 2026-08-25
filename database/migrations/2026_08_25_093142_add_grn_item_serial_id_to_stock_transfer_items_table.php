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
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            // The specific physical unit being transferred, when the product
            // is serial-tracked — same grn_item_serials link used by Product
            // Assignment. serial_number is a denormalized snapshot so this
            // row keeps reading correctly even if the serial record changes.
            $table->foreignId('grn_item_serial_id')->nullable()->after('product_variant_id')->constrained('grn_item_serials')->nullOnDelete();
            $table->string('serial_number')->nullable()->after('grn_item_serial_id');
            $table->index(['grn_item_serial_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grn_item_serial_id');
            $table->dropColumn('serial_number');
        });
    }
};
