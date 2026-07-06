<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Categories ────────────────────────────────────────────────────────
        Schema::create('item_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // ── Buyers ────────────────────────────────────────────────────────────
        Schema::create('buyers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // ── Merchandisers ─────────────────────────────────────────────────────
        Schema::create('merchandisers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Units of Measurement ──────────────────────────────────────────────
        Schema::create('uom', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');              // e.g. Pieces, Carton, Set
            $table->string('symbol');            // e.g. pcs, ctn, set
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'symbol']);
        });

        // ── Master Items ──────────────────────────────────────────────────────
        Schema::create('master_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();

            // ── Identitas ─────────────────────────────────────────────────────
            $table->string('item_code')->unique();    // kode unik global
            $table->string('item_name');
            $table->string('sku')->nullable();        // Stock Keeping Unit
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();

            // ── Relasi ────────────────────────────────────────────────────────
            $table->foreignUuid('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignUuid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUuid('merchandiser_id')->nullable()->constrained('merchandisers')->nullOnDelete();
            $table->foreignUuid('uom_id')->nullable()->constrained('uom')->nullOnDelete();

            // ── Atribut visual ────────────────────────────────────────────────
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->string('finish')->nullable();       // e.g. Primed, Painted, Raw

            // ── Dimensi produk (dalam inches — bisa convert) ──────────────────
            $table->decimal('product_length', 10, 4)->nullable();   // panjang
            $table->decimal('product_width',  10, 4)->nullable();   // lebar
            $table->decimal('product_height', 10, 4)->nullable();   // tinggi/tebal
            $table->enum('product_unit', ['inches', 'mm', 'cm'])->default('inches');

            // ── Dimensi packing ───────────────────────────────────────────────
            $table->decimal('pack_length', 10, 4)->nullable();
            $table->decimal('pack_width',  10, 4)->nullable();
            $table->decimal('pack_height', 10, 4)->nullable();
            $table->enum('pack_unit', ['inches', 'mm', 'cm'])->default('inches');
            $table->integer('pack_qty')->nullable();                // qty per carton/pack

            // ── Berat ─────────────────────────────────────────────────────────
            $table->decimal('net_weight',   8, 4)->nullable();     // kg
            $table->decimal('gross_weight', 8, 4)->nullable();     // kg

            // ── Info tambahan ─────────────────────────────────────────────────
            $table->string('country_of_origin')->nullable();
            $table->string('hs_code')->nullable();                  // Harmonized System
            $table->jsonb('custom_attributes')->nullable();         // field bebas tambahan
            $table->string('image_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id');
            $table->index('category_id');
            $table->index('buyer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_items');
        Schema::dropIfExists('uom');
        Schema::dropIfExists('merchandisers');
        Schema::dropIfExists('buyers');
        Schema::dropIfExists('item_categories');
    }
};