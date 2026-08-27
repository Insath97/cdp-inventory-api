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
     * user_id and branch_id, but on environments where that table was
     * created before those columns were added to the file, the migrations
     * table marks it as already run and Laravel won't re-apply it. This
     * backfills whichever columns are missing on those environments while
     * staying a no-op where they already exist.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('product_assignments', 'user_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('person_name')->constrained('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('product_assignments', 'branch_id')) {
            Schema::table('product_assignments', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('branch_name')->constrained('branches')->nullOnDelete();
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
