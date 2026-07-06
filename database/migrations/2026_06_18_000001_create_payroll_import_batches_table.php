<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('period_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('payroll_count')->default(0);
            $table->unsignedInteger('attendance_summary_count')->default(0);
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->uuid('import_batch_id')->nullable()->after('period_id');
            $table->index('import_batch_id');
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->uuid('import_batch_id')->nullable()->after('employee_id');
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id']);
            $table->dropColumn('import_batch_id');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id']);
            $table->dropColumn('import_batch_id');
        });

        Schema::dropIfExists('payroll_import_batches');
    }
};
