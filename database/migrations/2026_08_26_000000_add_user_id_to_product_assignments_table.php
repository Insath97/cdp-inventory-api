<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The create-table migration for product_assignments already defines
     * user_id, but on environments where that table was created before
     * user_id was added to the file, the migrations table marks it as
     * already run and Laravel won't re-apply it. This backfills the
     * column on those environments while staying a no-op where it
     * already exists.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('product_assignments', 'user_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('person_name')->constrained('users')->nullOnDelete();
                $table->index(['user_id', 'branch_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_assignments', 'user_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
