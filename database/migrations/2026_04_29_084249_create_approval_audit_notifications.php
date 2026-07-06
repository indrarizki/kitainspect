<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Approval Workflows ────────────────────────────────────────────────
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inspection_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('approver_id')->constrained('users')->restrictOnDelete();
            $table->integer('step_order')->default(1); // 1=Reviewer, 2=Admin
            $table->enum('status', ['pending', 'approved', 'rejected', 'revised'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['inspection_order_id', 'step_order']);
        });

        // ── Audit Logs ────────────────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');        // e.g. inspection.submitted, approval.approved
            $table->string('module');        // inspection | approval | template | user
            $table->uuid('target_id')->nullable();
            $table->string('target_type')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at');

            $table->index(['module', 'action']);
            $table->index('user_id');
        });

        // ── Notifications ─────────────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('inspection_order_id')->nullable();
            $table->string('type');          // inspection.submitted | approval.approved | etc
            $table->string('title');
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_workflows');
    }
};