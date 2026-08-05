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
        if (Schema::hasTable('stock_takes')) {
            return;
        }

        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('take_number')->unique();
            $table->date('take_date');
            $table->enum('status', ['draft', 'in_progress', 'completed', 'approved']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('branch_id');
            $table->index('created_by');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
