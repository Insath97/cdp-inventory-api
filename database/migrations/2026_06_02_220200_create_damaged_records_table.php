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
        Schema::create('damaged_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('damage_number')->unique();
            $table->date('damage_date');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('reason');
            $table->enum('status', ['reported', 'approved', 'cancelled'])->default('reported');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id']);
            $table->index(['product_variant_id']);
            $table->index(['branch_id']);
            $table->index(['reported_by']);
            $table->index(['approved_by']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damaged_records');
    }
};
