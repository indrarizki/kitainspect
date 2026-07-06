<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeeImport;
use App\Exports\EmployeeExport;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Employee::where('company_id', $user->company_id);
        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }
        $employees = $query->get();

        return response()->json($employees);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $user = $request->user();

        if ($employee->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($employee);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'joinDate' => ['required', 'date'],
            'employeeStatus' => ['required', 'string', 'max:1'],
            'code' => ['required', 'string', 'max:17'],
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'max:1'],
            'dateOfBirth' => ['required', 'date'],
            'identityNumber' => ['required', 'string', 'max:27'],
            'identityType' => ['required', 'string', 'max:1'],
            'maritalStatus' => ['required', 'string', 'max:1'],
            'email' => ['required', 'email', 'max:255'],
            'leaveBalance' => ['nullable', 'integer'],
            'taxGroup' => ['required', 'string', 'max:3'],
            'resignDate' => ['nullable', 'date'],
            'haveOvertimeBenefit' => ['required', 'boolean'],
            'riskRatio' => ['required', 'string', 'max:3'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'roles' => ['nullable', 'array'],
            'profileImage' => ['nullable', 'string'],
            'profileSize' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'uuid'],
            'department_id' => ['nullable', 'uuid'],
            'joblevel_id' => ['nullable', 'uuid'],
            'jobtitle_id' => ['nullable', 'uuid'],
            'supervisor_id' => ['nullable', 'uuid'],
            'region_of_birth_id' => ['nullable', 'uuid'],
            'city_of_birth_id' => ['nullable', 'uuid'],
            'address_id' => ['nullable', 'uuid'],
        ]);

        $employee = null;

        DB::transaction(function () use ($data, $user, &$employee) {
            $payload = $data;
            $payload['id'] = (string) Str::uuid();
            $payload['company_id'] = $user->company_id;
            if (isset($payload['password'])) {
                $payload['password'] = Hash::make($payload['password']);
            }

            $employee = Employee::create($payload);
        });

        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $user = $request->user();

        if ($employee->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'joinDate' => ['sometimes', 'date'],
            'employeeStatus' => ['sometimes', 'string', 'max:1'],
            'code' => ['sometimes', 'string', 'max:17'],
            'fullName' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', 'max:1'],
            'dateOfBirth' => ['sometimes', 'date'],
            'identityNumber' => ['sometimes', 'string', 'max:27'],
            'identityType' => ['sometimes', 'string', 'max:1'],
            'maritalStatus' => ['sometimes', 'string', 'max:1'],
            'email' => ['sometimes', 'email', 'max:255'],
            'leaveBalance' => ['nullable', 'integer'],
            'taxGroup' => ['sometimes', 'string', 'max:3'],
            'resignDate' => ['nullable', 'date'],
            'haveOvertimeBenefit' => ['sometimes', 'boolean'],
            'riskRatio' => ['sometimes', 'string', 'max:3'],
            'username' => ['sometimes', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'roles' => ['nullable', 'array'],
            'profileImage' => ['nullable', 'string'],
            'profileSize' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'uuid'],
            'department_id' => ['nullable', 'uuid'],
            'joblevel_id' => ['nullable', 'uuid'],
            'jobtitle_id' => ['nullable', 'uuid'],
            'supervisor_id' => ['nullable', 'uuid'],
            'region_of_birth_id' => ['nullable', 'uuid'],
            'city_of_birth_id' => ['nullable', 'uuid'],
            'address_id' => ['nullable', 'uuid'],
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        DB::transaction(function () use ($data, $employee) {
            if (isset($data['password']) && $data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $employee->update($data);
        });

        return response()->json($employee);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $user = $request->user();

        if ($employee->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $employee->delete();

        return response()->json(null, 204);
    }

    public function exportData(Request $request)
    {
        $user = $request->user();

        return Excel::download(new EmployeeExport($user->company_id), 'employees.xlsx');
    }

    public function importData(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        Excel::import(new EmployeeImport($user->company_id), $request->file('file'));

        return response()->json(['message' => 'Data karyawan berhasil diimpor.']);
    }

}
