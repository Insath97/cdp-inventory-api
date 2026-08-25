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
        Schema::table('product_assignments', function (Blueprint $table) {
            $table->foreignId('grn_item_serial_id')->nullable()->after('product_variant_id')->constrained('grn_item_serials')->nullOnDelete();
            $table->index(['grn_item_serial_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grn_item_serial_id');
        });
    }
};
