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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('supplier_id')->constrained('brands')->nullOnDelete();
            $table->foreignId('main_category_id')->nullable()->after('brand_id')->constrained('main_categories')->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->after('main_category_id')->constrained('sub_categories')->nullOnDelete();
            $table->foreignId('measurement_id')->nullable()->after('sub_category_id')->constrained('measurement_units')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('measurement_id')->constrained('units')->nullOnDelete();
            $table->foreignId('container_id')->nullable()->after('unit_id')->constrained('containers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('main_category_id');
            $table->dropConstrainedForeignId('sub_category_id');
            $table->dropConstrainedForeignId('measurement_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('container_id');
        });
    }
};
