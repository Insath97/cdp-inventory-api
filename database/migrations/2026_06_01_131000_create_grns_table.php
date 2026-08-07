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
        Schema::create('grns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('received_by')->constrained('users');
            $table->string('grn_number')->unique();
            $table->string('batch_number')->nullable();
            $table->date('received_date');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('supplier_bill_number')->nullable();
            $table->string('bill_image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grns');
    }
};