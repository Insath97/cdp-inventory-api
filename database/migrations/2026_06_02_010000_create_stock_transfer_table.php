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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();

            $table->enum('transfer_type', [
                'branch_to_branch',
                'employee_to_employee',
                'branch_to_employee',
                'employee_to_branch',
            ])->default('branch_to_branch');

            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('from_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transfer_number')->unique();
            $table->date('transfer_date');
            $table->enum('status', ['draft', 'approved', 'in_transit', 'received', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['from_branch_id']);
            $table->index(['to_branch_id']);
            $table->index(['requested_by']);
            $table->index(['approved_by']);
            $table->index(['status']);
            $table->index(['transfer_type']);
            $table->index(['from_employee_id']);
            $table->index(['to_employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
