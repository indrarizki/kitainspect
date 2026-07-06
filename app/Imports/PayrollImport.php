<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\AttendanceSummary;

use Illuminate\Support\Collection;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithStartRow;

class PayrollImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    protected $periodId;
    protected $batchId;
    protected $payrollCount = 0;
    protected $attendanceSummaryCount = 0;

    public function __construct($periodId, $batchId)
    {
        $this->periodId = $periodId;
        $this->batchId = $batchId;
    }

    public function startRow(): int
    {
        return 9;
    }

    public function collection(Collection $rows)
    {
        $components = SalaryComponent::pluck('id', 'code');

        $period = PayrollPeriod::where('id', $this->periodId)->first();

        foreach ($rows as $row) {

            if (empty(array_filter($row->toArray()))) {
                continue;
            }

            $employeeCode = trim($row[2] ?? '');

            $employee = Employee::where('code', $employeeCode)->first();

            if (!$employee) {
                continue;
            }

            
            $payroll = Payroll::create([
                'employee_id'    => $employee->id,
                'period_id'      => $this->periodId,
                'takeHomePay'    => $row[15] ?? 0,
                'takeHomePayKey' => 'THP',
                'import_batch_id' => $this->batchId,
                ]);
            $this->payrollCount++;
                
            $AttendanceSummary = AttendanceSummary::create([
                'employee_id'    => $employee->id,
                'year'      => $period->year,
                'month'    => $period->month,
                'totalWorkday' => $row[6]  ?? 0,
                'import_batch_id' => $this->batchId,
                ]);
            $this->attendanceSummaryCount++;
            
            $detailMap = [
                'UPAH'    => $row[7]  ?? 0,
                'TRANS'   => $row[8]  ?? 0,
                'TABUNG'  => $row[9]  ?? 0,
                'KASBON'  => $row[10] ?? 0,
                'APD'     => $row[11] ?? 0,
                'BPJSTK'  => $row[12] ?? 0,
                'BPJSKS'  => $row[13] ?? 0,
                'KOREKSI' => $row[14] ?? 0,
            ];

            foreach ($detailMap as $componentCode => $value) {

                if ($value === null || $value === '') {
                    continue;
                }

                PayrollDetail::create([
                    'payroll_id'    => $payroll->id,
                    'component_id'  => $components[$componentCode] ?? null,
                    'benefitKey'    => $componentCode,
                    'benefitValue'  => $value,
                ]);
            }
        }
    }

    public function getPayrollCount(): int
    {
        return $this->payrollCount;
    }

    public function getAttendanceSummaryCount(): int
    {
        return $this->attendanceSummaryCount;
    }
}
