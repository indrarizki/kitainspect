<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\AttendanceSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

class AttendanceImport implements ToCollection, WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }

    private function parseDate($value)
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            try {
                $dt = PhpSpreadsheetDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Exception $e) {
                // fallthrough
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty(array_filter($row->toArray()))) {
                continue;
            }

            $identifier = trim((string) ($row[0] ?? ''));

            $employee = null;

            if ($identifier !== '') {
                if (strlen($identifier) === 36) {
                    $employee = Employee::find($identifier);
                }

                if (! $employee) {
                    $employee = Employee::where('code', $identifier)->first();
                }
            }

            if (! $employee) {
                continue;
            }

            $attendanceDate = $this->parseDate($row[1] ?? null);

            if (! $attendanceDate) {
                continue;
            }

            $attendance = Attendance::create([
                'id' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'attendanceDate' => $attendanceDate->toDateString(),
                'checkIn' => $row[2] ?? null,
                'checkOut' => $row[3] ?? null,
                'absent' => isset($row[4]) ? (bool) $row[4] : false,
                'description' => $row[5] ?? null,
                'shiftment_id' => $row[6] ?? null,
            ]);

            // Recalculate summary for this employee/month
            $year = (int) $attendanceDate->format('Y');
            $month = (int) $attendanceDate->format('m');

            $totalWorkday = Attendance::where('employee_id', $employee->id)
                ->whereYear('attendanceDate', $year)
                ->whereMonth('attendanceDate', $month)
                ->count();

            $totalIn = Attendance::where('employee_id', $employee->id)
                ->whereYear('attendanceDate', $year)
                ->whereMonth('attendanceDate', $month)
                ->where('absent', false)
                ->whereNotNull('checkIn')
                ->count();

            $totalAbsent = Attendance::where('employee_id', $employee->id)
                ->whereYear('attendanceDate', $year)
                ->whereMonth('attendanceDate', $month)
                ->where('absent', true)
                ->count();

            AttendanceSummary::updateOrCreate(
                ['employee_id' => $employee->id, 'year' => $year, 'month' => $month],
                [
                    'totalWorkday' => $totalWorkday,
                    'totalIn' => $totalIn,
                    'totalLoyalty' => $totalIn,
                    'totalAbsent' => $totalAbsent,
                    'totalOvertime' => 0,
                ]
            );
        }
    }
}
