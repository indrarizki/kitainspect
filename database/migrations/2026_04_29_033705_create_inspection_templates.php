<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Inspection Templates ───────────────────────────────────────────────
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0');
            $table->enum('sampling_method', ['aql_2_5', 'check_all', 'custom'])->default('aql_2_5');
            $table->decimal('custom_sample_pct', 5, 2)->nullable(); // jika custom
            $table->string('inspection_level')->default('II');       // AQL level: I | II | III
            $table->enum('default_unit', ['mm', 'inches', 'cm'])->default('inches');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        // ── Template Sections ─────────────────────────────────────────────────
        // Contoh: A=Order Detail, B=Measurement Check, C=Visual Inspection, dll
        Schema::create('template_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('inspection_templates')->cascadeOnDelete();
            $table->string('code', 10);          // A, B, C, D...
            $table->string('title');             // "Order Detail", "Measurement Check"
            $table->enum('section_type', [
                'order_detail',    // info PO, produk, qty
                'measurement',     // pengukuran dengan target & range
                'visual',          // inspeksi visual, defect, foto
                'deformation',     // crook, bow, cup, twist
                'remarks',         // catatan inspector per item
                'evidence',        // upload foto/bukti
                'aql_summary',     // ringkasan AQL pass/fail
                'signature',       // tanda tangan
                'custom',          // bebas
            ])->default('custom');
            $table->integer('order_index')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'code']);
        });

        // ── Template Checkpoints (per section) ────────────────────────────────
        Schema::create('template_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('section_id')->constrained('template_sections')->cascadeOnDelete();
            $table->string('label');             // "Thickness", "Width", "Bad Repair"
            $table->enum('input_type', [
                'number',       // pengukuran numerik
                'text',         // teks bebas
                'select',       // pilihan dropdown
                'checkbox',     // ya/tidak
                'photo',        // upload foto
                'signature',    // tanda tangan
                'rating',       // rating 1-5
            ])->default('text');

            // Unit & conversion
            $table->enum('unit', ['mm', 'inches', 'cm', 'degrees', 'pcs', 'percent', 'none'])->default('none');
            $table->boolean('allow_unit_conversion')->default(false);

            // Target & toleransi (untuk measurement)
            $table->decimal('target_value', 10, 4)->nullable();
            $table->decimal('min_value', 10, 4)->nullable();     // range bawah
            $table->decimal('max_value', 10, 4)->nullable();     // range atas

            // Visual inspection
            $table->enum('severity', ['minor', 'major', 'critical'])->nullable(); // untuk defect

            // Settings
            $table->boolean('is_required')->default(false);
            $table->boolean('has_photo')->default(false);       // wajib foto jika out of range
            $table->jsonb('options')->nullable();                 // pilihan untuk select
            $table->text('description')->nullable();             // panduan inspector
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_checkpoints');
        Schema::dropIfExists('template_sections');
        Schema::dropIfExists('inspection_templates');
    }
};