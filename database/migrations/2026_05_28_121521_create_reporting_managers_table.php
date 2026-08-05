<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: The "users" hierarchy fields (branch_id, parent_user_id, zone_id,
     * region_id, province_id) and the reporting_manager_id FK re-target are
     * attached here instead of inside create_users_table, because the users
     * table (2026_03_06) is created before both branches (2026_05_21) and
     * reporting_managers (2026_05_28). This is the earliest point in the
     * migration order where both target tables already exist, so the FKs
     * can be added safely on a fresh install.
     */
    public function up(): void
    {
        Schema::create('reporting_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->foreignId('reporting_manager_id')->nullable()->constrained('reporting_managers')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->boolean('can_login')->default(true);
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // --- merged from update_reporting_manager_fk_and_add_hierarchy_fields_to_users_table ---
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['reporting_manager_id']);
            });
        } catch (\Exception $e) {
        }

        DB::table('users')->whereNotIn('reporting_manager_id', function ($query) {
            $query->select('id')->from('reporting_managers');
        })->update(['reporting_manager_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('reporting_manager_id')
                ->references('id')
                ->on('reporting_managers')
                ->onDelete('set null');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('reporting_manager_id')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'parent_user_id')) {
                $table->foreignId('parent_user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('parent_user_id');
            }
            if (! Schema::hasColumn('users', 'region_id')) {
                $table->unsignedBigInteger('region_id')->nullable()->after('zone_id');
            }
            if (! Schema::hasColumn('users', 'province_id')) {
                $table->unsignedBigInteger('province_id')->nullable()->after('region_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn(['branch_id', 'parent_user_id', 'zone_id', 'region_id', 'province_id']);
        });

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['reporting_manager_id']);
            });
        } catch (\Exception $e) {
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('reporting_manager_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::dropIfExists('reporting_managers');
    }
};
