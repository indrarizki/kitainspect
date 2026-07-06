<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Inspection Orders (header / satu sesi inspeksi) ───────────────────
        Schema::create('inspection_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('inspection_templates')->restrictOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();

            // Identitas PO
            $table->string('po_number')->nullable();
            $table->string('customer')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('product_name')->nullable();
            $table->date('inspection_date')->nullable();
            $table->string('location')->nullable();

            // Status & method
            $table->enum('status', [
                'draft',
                'in_progress',
                'submitted',
                'in_review',
                'approved',
                'rejected',
            ])->default('draft');

            // Sampling — bisa override dari template
            $table->enum('sampling_method', ['aql_2_5', 'check_all', 'custom'])->nullable();
            $table->string('inspection_level')->nullable(); // override AQL level
            $table->decimal('custom_sample_pct', 5, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('company_id');
        });

        // ── Inspection Items (per produk/SKU dalam satu order) ────────────────
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_order_id')->constrained()->cascadeOnDelete();
            $table->integer('item_no');              // 1, 2, 3...
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->string('drawing_number')->nullable();
            $table->string('dimensions')->nullable(); // "36 x 96 x 1-3/8"

            // Qty
            $table->integer('produced_qty');
            $table->integer('inspected_qty')->nullable();  // auto-hitung dari AQL
            $table->decimal('inspected_pct', 5, 2)->nullable();

            // AQL result (auto-hitung)
            $table->integer('aql_accept_no')->nullable();
            $table->integer('aql_reject_no')->nullable();
            $table->integer('total_minor_defects')->default(0);
            $table->integer('total_major_defects')->default(0);
            $table->integer('total_critical_defects')->default(0);
            $table->enum('result', ['pass', 'fail', 'conditional', 'pending'])->default('pending');

            $table->text('inspector_remarks')->nullable();
            $table->timestamps();

            $table->unique(['inspection_order_id', 'item_no']);
        });

        // ── Checkpoint Responses (jawaban per checkpoint per sample per item) ─
        Schema::create('checkpoint_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('checkpoint_id')->constrained('template_checkpoints')->cascadeOnDelete();
            $table->integer('sample_no')->default(1);   // sample ke-1, ke-2, ke-3

            // Nilai jawaban
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 12, 4)->nullable();
            $table->enum('value_unit', ['mm', 'inches', 'cm', 'degrees', 'pcs', 'percent', 'none'])->nullable();

            // Setelah konversi ke unit standar template
            $table->decimal('value_converted', 12, 4)->nullable();

            // Auto-flag
            $table->boolean('is_out_of_range')->default(false);
            $table->boolean('is_pass')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inspection_item_id', 'checkpoint_id', 'sample_no']);
        });

        // ── Defect Records (visual inspection defects) ────────────────────────
        Schema::create('defect_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('checkpoint_id')->nullable()->constrained('template_checkpoints')->nullOnDelete();
            $table->integer('sample_no')->default(1);
            $table->string('defect_type');               // "Bad Repair", "Missing Paint", "Delamination"
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->integer('qty')->default(1);
            $table->enum('action', ['repair', 'reject', 'accept'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // ── Inspection Evidences (foto bukti) ─────────────────────────────────
        Schema::create('inspection_evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('checkpoint_id')->nullable()->constrained('template_checkpoints')->nullOnDelete();
            $table->string('caption')->nullable();        // "Thickness", "Bad Repair", "Product Label"
            $table->string('file_url');
            $table->string('file_type')->default('photo'); // photo | document
            $table->timestamp('taken_at')->nullable();    // dari metadata foto
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_evidences');
        Schema::dropIfExists('defect_records');
        Schema::dropIfExists('checkpoint_responses');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspection_orders');
    }
};