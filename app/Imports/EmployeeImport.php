<?php

namespace App\Imports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

class EmployeeImport implements ToModel, WithStartRow
{
    protected $companyId;

    public function __construct($companyId)
    {
        $this->companyId = $companyId;
    }

     public function startRow(): int
    {
        return 4;
    }

    private function parseDate($value)
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        if (is_numeric($value)) {
            if (strlen((string) intval($value)) === 8) {
                try {
                    return Carbon::createFromFormat('Ymd', $value)->startOfDay();
                } catch (\Exception $e) {

                }
            }

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

    public function model(array $row)
    {
        // Skip completely empty rows (avoid inserting records with required null fields)
        $hasAny = false;
        foreach ($row as $cell) {
            if (!blank($cell) && $cell !== '') {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return null;
        }

        $code = isset($row[2]) ? trim((string) $row[2]) : null;

        // The employee code is the identifier used to match existing records.
        // Without it we cannot safely update, so the row is skipped.
        if (blank($code)) {
            return null;
        }

        $attributes = [
            'code'                => $code,
            'fullName'            => $row[3] ?? null,
            'joinDate'            => $this->parseDate($row[4] ?? null),
            'employeeStatus'      => $row[5] ?? null,
            'gender'              => $row[6] ?? null,
            'dateOfBirth'         => $this->parseDate($row[7] ?? null),
            'identityNumber'      => $row[8] ?? null,
            'identityType'        => $row[9] ?? null,
            'maritalStatus'       => $row[10] ?? null,
            'leaveBalance'        => isset($row[11]) ? (int) $row[11] : 0,
            'taxGroup'            => $row[12] ?? null,
            'resignDate'          => $this->parseDate($row[13] ?? null),
            'haveOvertimeBenefit' => filter_var($row[14] ?? false, FILTER_VALIDATE_BOOLEAN),
            'email'               => $row[15] ?? null,
            'company_id'          => $this->companyId,
        ];

        $employee = Employee::where('company_id', $this->companyId)
            ->where('code', $code)
            ->first();

        // Existing employee: only persist when the imported data actually differs.
        if ($employee) {
            $employee->fill($attributes);

            if ($employee->isDirty()) {
                $employee->save();
            }

            // Returning null prevents the importer from inserting a duplicate row.
            return null;
        }

        // New employee code: insert a fresh record.
        return new Employee($attributes);
    }
}
