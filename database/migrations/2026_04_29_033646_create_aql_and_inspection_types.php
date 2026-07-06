<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── AQL Lookup Table (seeded once, never changes) ─────────────────────
        Schema::create('aql_tables', function (Blueprint $table) {
            $table->id();
            $table->integer('lot_size_from');
            $table->integer('lot_size_to');
            $table->string('inspection_level')->default('II'); // I | II | III
            $table->integer('sample_size');
            $table->integer('accept_no');   // Ac
            $table->integer('reject_no');   // Re
        });

        // ── Inspection Types (Final, Inline, Sample, Pre-shipment) ────────────
        Schema::create('inspection_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_types');
        Schema::dropIfExists('aql_tables');
    }
};