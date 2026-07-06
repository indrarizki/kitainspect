<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollImportBatch;
use App\Models\PayrollPeriod;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PayrollImport;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function indexPeriod(Request $request): JsonResponse
    {
        $user = $request->user();

        $payrolls = PayrollPeriod::where('company_id', $user->company_id)
            ->get();

        return response()->json($payrolls);
    }

    public function show(Request $request, Payroll $payroll): JsonResponse
    {
        $user = $request->user();

        if ($payroll->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $payroll->load('period', 'details.employee');

        return response()->json($payroll);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'period_id' => ['required', 'exists:payroll_periods,id'],
            'employee_ids' => ['required', 'array'],
            'employee_ids.*' => ['exists:employees,id'],
        ]);

        // Ensure the period belongs to the user's company
        $period = PayrollPeriod::where('company_id', $user->company_id)->findOrFail($data['period_id']);

        DB::beginTransaction();
        try {
            $payroll = Payroll::create([
                'company_id' => $user->company_id,
                'period_id' => $data['period_id'],
            ]);

            foreach ($data['employee_ids'] as $employeeId) {
                PayrollDetail::create([
                    'benefitValue' => 0,
                    'benefitKey' => null, // Placeholder, actual calculation logic needed
                    'payroll_id' => $payroll->id,
                    // Additional fields like salary, deductions can be calculated here
                ]);
            }

            DB::commit();
            return response()->json($payroll->load('period', 'details.employee'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create payroll', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Payroll $payroll): JsonResponse
    {
        $user = $request->user();

        if ($payroll->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $payroll->delete();

        return response()->json(['message' => 'Payroll deleted successfully.']);
    }

    public function indexPayrolls(Request $request, PayrollPeriod $period): JsonResponse
    {
        $user = $request->user();

        if ($period->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $payrolls = Payroll::query()
            ->select(['id', 'employee_id', 'period_id', 'takeHomePay'])
            ->with([
                'employee:id,code,fullName,department_id',
                'employee.department:id,name',
            ])
            ->where('period_id', $period->id)
            ->get();

        return response()->json($payrolls);
    }

    public function payrollDetails(Request $request, Payroll $payroll): JsonResponse
    {
        $payroll->load([
            'employee',
            'period',
        ]);

        $details = PayrollDetail::with([
            'salaryComponent',
        ])
        ->where('payroll_id', $payroll->id)
        ->get();

        $attendanceSummary = AttendanceSummary::where('employee_id', $payroll->employee_id)
            ->where('month', $payroll->period->month)
            ->where('year', $payroll->period->year)
            ->first();

        return response()->json([
            'payroll' => $payroll,
            'details' => $details,
            'attendance_summary' => $attendanceSummary,
        ]);
    }

    public function importData(Request $request, PayrollPeriod $period): JsonResponse
    {
        $user = $request->user();

        if ($period->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        try {
            $batch = DB::transaction(function () use ($period, $request, $user) {
                $batch = PayrollImportBatch::create([
                    'period_id' => $period->id,
                    'company_id' => $user->company_id,
                    'uploaded_by' => $user->id,
                    'file_name' => $request->file('file')->getClientOriginalName(),
                ]);

                $import = new PayrollImport($period->id, $batch->id);

                Excel::import($import, $request->file('file'));

                $batch->update([
                    'payroll_count' => $import->getPayrollCount(),
                    'attendance_summary_count' => $import->getAttendanceSummaryCount(),
                ]);

                return $batch->fresh();
            });

            return response()->json([
                'message' => 'Payroll data imported successfully.',
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to import payroll data', 'error' => $e->getMessage()], 500);
        }
    }

    public function rollbackLastImport(Request $request, ?PayrollPeriod $period = null): JsonResponse
    {
        $user = $request->user();

        if ($period && $period->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $batchQuery = PayrollImportBatch::where('company_id', $user->company_id)
            ->whereNull('rolled_back_at');

        if ($period) {
            $batchQuery->where('period_id', $period->id);
        }

        $batch = $batchQuery->latest()->first();

        if (!$batch) {
            return response()->json(['message' => 'No payroll import batch available to rollback.'], 404);
        }

        try {
            $result = DB::transaction(function () use ($batch) {
                $payrollIds = Payroll::where('import_batch_id', $batch->id)->pluck('id');

                $payrollDetailCount = PayrollDetail::whereIn('payroll_id', $payrollIds)->count();
                $payrollCount = $payrollIds->count();
                $attendanceSummaryCount = AttendanceSummary::where('import_batch_id', $batch->id)->count();

                PayrollDetail::whereIn('payroll_id', $payrollIds)->delete();
                Payroll::whereIn('id', $payrollIds)->delete();
                AttendanceSummary::where('import_batch_id', $batch->id)->delete();

                $batch->update([
                    'rolled_back_at' => now(),
                ]);

                return [
                    'batch' => $batch->fresh(),
                    'deleted' => [
                        'payrolls' => $payrollCount,
                        'payroll_details' => $payrollDetailCount,
                        'attendance_summaries' => $attendanceSummaryCount,
                    ],
                ];
            });

            return response()->json([
                'message' => 'Last payroll import rolled back successfully.',
                'batch' => $result['batch'],
                'deleted' => $result['deleted'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to rollback payroll import', 'error' => $e->getMessage()], 500);
        }
    }
}
