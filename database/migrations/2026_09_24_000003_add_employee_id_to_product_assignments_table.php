<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_assignments', 'employee_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_assignments', 'employee_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
