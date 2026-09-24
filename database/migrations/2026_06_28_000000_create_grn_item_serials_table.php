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
        // Guarded: skip if the table already exists (databases that ran the
        // original 2026_08_05 migration).
        if (Schema::hasTable('grn_item_serials')) {
            return;
        }

        Schema::create('grn_item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_item_id')->constrained('grn_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('serial_number');
            $table->timestamps();

            // A given variant's individual units must each have a distinct
            // serial — without this, two received units could silently share
            // an identity, defeating the point of capturing one at all.
            $table->unique(['product_variant_id', 'serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropped by the later 2026_08_05 migration's down().
        Schema::dropIfExists('grn_item_serials');
    }
};
