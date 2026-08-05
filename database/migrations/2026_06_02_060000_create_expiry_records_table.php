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
        Schema::create('expiry_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_item_id')->nullable()->constrained('grn_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->enum('status', ['active', 'expired', 'disposed'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['grn_item_id']);
            $table->index(['product_id']);
            $table->index(['product_variant_id']);
            $table->index(['branch_id']);
            $table->index(['expiry_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiry_records');
    }
};
