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
            $table->string('person_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('group_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('department_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('product_variant_id')->constrained('products')->onDelete('cascade');
            $table->string('serial_number')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('product_name')->nullable();
            $table->date('issue_date')->useCurrent();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'branch_id', 'is_active']);
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