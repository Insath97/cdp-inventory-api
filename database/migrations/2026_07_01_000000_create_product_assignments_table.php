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
        Schema::create('product_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code')->unique();
            $table->string('person_name');
            $table->string('group_name')->nullable();
            $table->string('branch_name');
            $table->string('department_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('product_variant_id')->constrained('products')->onDelete('cascade');
            $table->string('product_sku')->nullable();
            $table->string('product_name')->nullable();
            $table->date('issue_date')->useCurrent();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_assignments');
    }
};