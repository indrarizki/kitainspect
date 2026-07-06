<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\Shiftment;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShiftOvertimeController extends Controller
{
    public function shiftments(Request $request): JsonResponse
    {
        $query = Shiftment::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy('startHour')->orderBy('code');

        return response()->json($this->maybePaginate($request, $query));
    }

    public function storeShiftment(Request $request): JsonResponse
    {
        $data = $request->validate($this->shiftmentRules());
        $data['id'] = (string) Str::uuid();

        $shiftment = Shiftment::create($data);

        return response()->json($shiftment, 201);
    }

    public function showShiftment(string $shiftment): JsonResponse
    {
        return response()->json(Shiftment::findOrFail($shiftment));
    }

    public function updateShiftment(Request $request, string $shiftment): JsonResponse
    {
        $item = Shiftment::findOrFail($shiftment);
        $data = $request->validate($this->shiftmentRules(true));

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        $item->update($data);

        return response()->json($item->fresh());
    }

    public function destroyShiftment(string $shiftment): JsonResponse
    {
        Shiftment::findOrFail($shiftment)->delete();

        return response()->json(null, 204);
    }

    public function workShifts(Request $request): JsonResponse
    {
        $query = WorkShift::query()
            ->with($this->scheduleRelations())
            ->whereHas('employee', $this->employeeCompanyScope($request));

        $this->applyEmployeeFilters($request, $query);
        $this->applyDateRange($request, $query, 'startDate', 'endDate');

        $query->orderByDesc('startDate')->orderBy('endDate');

        return response()->json($this->maybePaginate($request, $query));
    }

    public function storeWorkShift(Request $request): JsonResponse
    {
        $data = $request->validate($this->workShiftRules($request));
        $this->abortIfEmployeeIsOutOfCompany($request, $data['employee_id']);

        $data['id'] = (string) Str::uuid();
        $workShift = WorkShift::create($data)->load($this->scheduleRelations());

        return response()->json($workShift, 201);
    }

    public function showWorkShift(Request $request, string $workshift): JsonResponse
    {
        return response()->json($this->findWorkShift($request, $workshift));
    }

    public function updateWorkShift(Request $request, string $workshift): JsonResponse
    {
        $item = $this->findWorkShift($request, $workshift);
        $data = $request->validate($this->workShiftRules($request, true));

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        if (isset($data['employee_id'])) {
            $this->abortIfEmployeeIsOutOfCompany($request, $data['employee_id']);
        }

        $item->update($data);

        return response()->json($item->fresh($this->scheduleRelations()));
    }

    public function destroyWorkShift(Request $request, string $workshift): JsonResponse
    {
        $this->findWorkShift($request, $workshift)->delete();

        return response()->json(null, 204);
    }

    public function overtimes(Request $request): JsonResponse
    {
        $query = Overtime::query()
            ->with($this->overtimeRelations())
            ->whereHas('employee', $this->employeeCompanyScope($request));

        $this->applyEmployeeFilters($request, $query);
        $this->applyDateRange($request, $query, 'overtimeDate', 'overtimeDate');

        $query->orderByDesc('overtimeDate')->orderBy('startHour');

        return response()->json($this->maybePaginate($request, $query));
    }

    public function storeOvertime(Request $request): JsonResponse
    {
        $data = $request->validate($this->overtimeRules($request));
        $this->abortIfEmployeeIsOutOfCompany($request, $data['employee_id']);
        $this->abortIfEmployeeIsOutOfCompany($request, $data['approved_by_id'] ?? null);

        $data['id'] = (string) Str::uuid();
        $data['holiday'] = (bool) ($data['holiday'] ?? false);
        $data['overday'] = (bool) ($data['overday'] ?? false);

        $overtime = Overtime::create($data)->load($this->overtimeRelations());

        return response()->json($overtime, 201);
    }

    public function showOvertime(Request $request, string $overtime): JsonResponse
    {
        return response()->json($this->findOvertime($request, $overtime));
    }

    public function updateOvertime(Request $request, string $overtime): JsonResponse
    {
        $item = $this->findOvertime($request, $overtime);
        $data = $request->validate($this->overtimeRules($request, true));

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        if (isset($data['employee_id'])) {
            $this->abortIfEmployeeIsOutOfCompany($request, $data['employee_id']);
        }

        if (array_key_exists('approved_by_id', $data)) {
            $this->abortIfEmployeeIsOutOfCompany($request, $data['approved_by_id']);
        }

        if (array_key_exists('holiday', $data)) {
            $data['holiday'] = (bool) $data['holiday'];
        }

        if (array_key_exists('overday', $data)) {
            $data['overday'] = (bool) $data['overday'];
        }

        $item->update($data);

        return response()->json($item->fresh($this->overtimeRelations()));
    }

    public function destroyOvertime(Request $request, string $overtime): JsonResponse
    {
        $this->findOvertime($request, $overtime)->delete();

        return response()->json(null, 204);
    }

    protected function shiftmentRules(bool $partial = false): array
    {
        return $this->partialRules([
            'code' => ['required', 'string', 'max:7'],
            'name' => ['required', 'string', 'max:255'],
            'startHour' => ['required', 'date_format:H:i'],
            'endHour' => ['required', 'date_format:H:i'],
        ], $partial);
    }

    protected function workShiftRules(Request $request, bool $partial = false): array
    {
        return $this->partialRules([
            'employee_id' => ['required', 'uuid'],
            'shiftment_id' => ['required', 'uuid', 'exists:shiftments,id'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'description' => ['nullable', 'string', 'max:255'],
        ], $partial);
    }

    protected function overtimeRules(Request $request, bool $partial = false): array
    {
        return $this->partialRules([
            'employee_id' => ['required', 'uuid'],
            'shiftment_id' => ['nullable', 'uuid', 'exists:shiftments,id'],
            'approved_by_id' => ['nullable', 'uuid'],
            'overtimeDate' => ['required', 'date'],
            'startHour' => ['required', 'date_format:H:i'],
            'endHour' => ['required', 'date_format:H:i'],
            'rawValue' => ['required', 'numeric', 'min:0'],
            'calculatedValue' => ['required', 'numeric', 'min:0'],
            'holiday' => ['boolean'],
            'overday' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ], $partial);
    }

    protected function partialRules(array $rules, bool $partial): array
    {
        if (! $partial) {
            return $rules;
        }

        return collect($rules)
            ->map(fn (array $ruleSet) => array_merge(['sometimes'], $ruleSet))
            ->all();
    }

    protected function maybePaginate(Request $request, Builder $query)
    {
        if (! $request->boolean('paginate')) {
            return $query->get();
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return $query->paginate($perPage);
    }

    protected function applyEmployeeFilters(Request $request, Builder $query): void
    {
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id')->toString());
        }

        if ($request->filled('shiftment_id')) {
            $query->where('shiftment_id', $request->string('shiftment_id')->toString());
        }
    }

    protected function applyDateRange(Request $request, Builder $query, string $fromColumn, string $toColumn): void
    {
        if ($request->filled('date_from')) {
            $query->where($toColumn, '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->where($fromColumn, '<=', $request->date('date_to')->toDateString());
        }
    }

    protected function employeeCompanyScope(Request $request): callable
    {
        return fn (Builder $query) => $query->where('company_id', $request->user()->company_id);
    }

    protected function abortIfEmployeeIsOutOfCompany(Request $request, ?string $employeeId): void
    {
        if (! $employeeId) {
            return;
        }

        $exists = Employee::where('company_id', $request->user()->company_id)
            ->where('id', $employeeId)
            ->exists();

        abort_unless($exists, 422, 'Employee is not available for your company.');
    }

    protected function findWorkShift(Request $request, string $id): WorkShift
    {
        return WorkShift::query()
            ->with($this->scheduleRelations())
            ->whereHas('employee', $this->employeeCompanyScope($request))
            ->findOrFail($id);
    }

    protected function findOvertime(Request $request, string $id): Overtime
    {
        return Overtime::query()
            ->with($this->overtimeRelations())
            ->whereHas('employee', $this->employeeCompanyScope($request))
            ->findOrFail($id);
    }

    protected function scheduleRelations(): array
    {
        return [
            'employee:id,code,fullName,company_id',
            'shiftment:id,code,name,startHour,endHour',
        ];
    }

    protected function overtimeRelations(): array
    {
        return [
            'employee:id,code,fullName,company_id',
            'shiftment:id,code,name,startHour,endHour',
            'approvedBy:id,code,fullName,company_id',
        ];
    }
}
