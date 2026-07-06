<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Imports\AttendanceImport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AttendanceController extends Controller
{
	public function index(Request $request)
	{
		$query = Attendance::with(['employee', 'shiftment'])->orderBy('attendanceDate', 'desc');

		if ($request->filled('employee_id')) {
			$query->where('employee_id', $request->get('employee_id'));
		}

		if ($request->filled('start_date')) {
			$query->whereDate('attendanceDate', '>=', $request->get('start_date'));
		}

		if ($request->filled('end_date')) {
			$query->whereDate('attendanceDate', '<=', $request->get('end_date'));
		}

		$perPage = (int) $request->get('per_page', 15);

		return response()->json($query->paginate($perPage));
	}

	public function show($id)
	{
		$attendance = Attendance::with(['employee', 'shiftment'])->findOrFail($id);
		return response()->json($attendance);
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'attendanceDate' => 'required|date',
			'employee_id' => 'required|exists:employees,id',
			'checkIn' => 'nullable|string',
			'checkOut' => 'nullable|string',
			'absent' => 'sometimes|boolean',
			'description' => 'nullable|string',
			'shiftment_id' => 'nullable|exists:shiftments,id',
		]);

		$attendance = Attendance::create(array_merge($validated, ['id' => (string) Str::uuid()]));

		$this->recalculateSummaryForAttendance($attendance);

		return response()->json($attendance, 201);
	}

	public function update(Request $request, $id)
	{
		$attendance = Attendance::findOrFail($id);

		$validated = $request->validate([
			'attendanceDate' => 'sometimes|date',
			'employee_id' => 'sometimes|exists:employees,id',
			'checkIn' => 'nullable|string',
			'checkOut' => 'nullable|string',
			'absent' => 'sometimes|boolean',
			'description' => 'nullable|string',
			'shiftment_id' => 'nullable|exists:shiftments,id',
		]);

		$attendance->update($validated);

		$this->recalculateSummaryForAttendance($attendance);

		return response()->json($attendance);
	}

	public function exportData(Request $request)
	{
		$query = Attendance::with(['employee', 'shiftment'])->orderBy('attendanceDate', 'desc');

		if ($request->filled('employee_id')) {
			$query->where('employee_id', $request->get('employee_id'));
		}

		if ($request->filled('start_date')) {
			$query->whereDate('attendanceDate', '>=', $request->get('start_date'));
		}

		if ($request->filled('end_date')) {
			$query->whereDate('attendanceDate', '<=', $request->get('end_date'));
		}

		$fileName = 'attendances_' . now()->format('Ymd_His') . '.csv';

		$response = new StreamedResponse(function () use ($query) {
			$handle = fopen('php://output', 'w');
			// header row
			fputcsv($handle, ['id','employee_id','employee_code','attendanceDate','checkIn','checkOut','absent','description','shiftment_id']);

			foreach ($query->cursor() as $row) {
				fputcsv($handle, [
					$row->id,
					$row->employee_id,
					optional($row->employee)->code,
					optional($row->attendanceDate)->toDateString(),
					$row->checkIn,
					$row->checkOut,
					$row->absent ? 1 : 0,
					$row->description,
					$row->shiftment_id,
				]);
			}

			fclose($handle);
		});

		$response->headers->set('Content-Type', 'text/csv');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

		return $response;
	}

	public function importData(Request $request)
	{
		$request->validate([
			'file' => 'required|file',
		]);

		$file = $request->file('file');

		Excel::import(new AttendanceImport, $file);

		return response()->json(['message' => 'Import finished']);
	}

	protected function recalculateSummaryForAttendance(Attendance $attendance)
	{
		$date = Carbon::parse($attendance->attendanceDate);
		$year = (int) $date->format('Y');
		$month = (int) $date->format('m');

		$employeeId = $attendance->employee_id;

		$totalWorkday = Attendance::where('employee_id', $employeeId)
			->whereYear('attendanceDate', $year)
			->whereMonth('attendanceDate', $month)
			->count();

		$totalIn = Attendance::where('employee_id', $employeeId)
			->whereYear('attendanceDate', $year)
			->whereMonth('attendanceDate', $month)
			->where('absent', false)
			->whereNotNull('checkIn')
			->count();

		$totalAbsent = Attendance::where('employee_id', $employeeId)
			->whereYear('attendanceDate', $year)
			->whereMonth('attendanceDate', $month)
			->where('absent', true)
			->count();

		AttendanceSummary::updateOrCreate(
			['employee_id' => $employeeId, 'year' => $year, 'month' => $month],
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
