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
        try {
            file_put_contents(storage_path('logs/employee_import_debug.log'), json_encode($row) . PHP_EOL, FILE_APPEND);
        } catch (\Exception $e) {
        }

        $joinDate = $this->parseDate($row[4] ?? null);
        $dateOfBirth = $this->parseDate($row[7] ?? null);
        $resignDate = $this->parseDate($row[13] ?? null);

        if (is_null($joinDate)) {
            try {
                file_put_contents(storage_path('logs/employee_import_debug.log'), "PARSE_JOINDATE_FAILED: " . ($row[4] ?? 'NULL') . PHP_EOL, FILE_APPEND);
            } catch (\Exception $e) {
            }
        }

        return new Employee([
            // Menggunakan array_get atau mencocokkan key slug asli hasil konversi Excel
            // 'id'                  => Str::uuid(),
            'code'                => $row[2] ?? null,
            'fullName'            => $row[3] ?? null,
            'joinDate'            => $joinDate,
            'employeeStatus'      => $row[5] ?? null,
            'gender'              => $row[6] ?? null,
            'dateOfBirth'         => $dateOfBirth,
            'identityNumber'      => $row[8] ?? null,
            'identityType'        => $row[9] ?? null,
            'maritalStatus'       => $row[10] ?? null,
            'leaveBalance'        => isset($row[11]) ? (int)$row[11] : 0,
            'taxGroup'            => $row[12] ?? null,
            'resignDate'          => $resignDate,
            'haveOvertimeBenefit' => filter_var($row[14] ?? false, FILTER_VALIDATE_BOOLEAN),
            'email'               => $row[15] ?? null,
            'company_id'          => $this->companyId,
        ]);
    }

    
}
