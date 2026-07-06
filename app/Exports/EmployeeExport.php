<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeExport implements FromCollection, WithHeadings
{
    protected int $companyId;
    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function collection()
    {
        return Employee::select('code', 'fullName', 'joinDate', 'departments.name as department_name', 
        'employeeStatus', 'gender', 'dateOfBirth', 'identityNumber', 'identityType', 'maritalStatus', 
        'email', 'leaveBalance', 'taxGroup', 'resignDate', 'haveOvertimeBenefit')
        ->join('departments', 'employees.department_id', '=', 'departments.id')
        ->where('company_id', $this->companyId)   
        ->get();
    }

    public function headings(): array
    {
        return [
            'Employee Code',
            'Full Name',
            'Join Date',
            'Department Name',
            'Employee Status',
            'Gender',
            'Date of Birth',
            'Identity Number',
            'Identity Type',
            'Marital Status',
            'Email',
            'Leave Balance',
            'Tax Group',
            'Resign Date',
            'Have Overtime Benefit',
        ];
    }
}
