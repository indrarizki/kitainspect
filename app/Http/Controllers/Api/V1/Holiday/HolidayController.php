<?php

namespace App\Http\Controllers\Api\V1\Holiday;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Holiday;

class HolidayController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		$query = Holiday::query();

		if ($request->filled('search')) {
			$query->where('name', 'ilike', "%{$request->search}%");
		}

		if ($request->filled('date')) {
			$query->whereDate('holidayDate', $request->date);
		}

		$holidays = $query->get();

		return response()->json($holidays);
	}

	public function show(Request $request, Holiday $holiday): JsonResponse
	{
		return response()->json($holiday);
	}

	public function store(Request $request): JsonResponse
	{
		$data = $request->validate([
			'holidayDate' => ['required', 'date'],
			'name' => ['required', 'string', 'max:255'],
		]);

		$holiday = null;

		DB::transaction(function () use ($data, &$holiday) {
			$payload = $data;
			$payload['id'] = (string) Str::uuid();

			$holiday = Holiday::create($payload);
		});

		return response()->json($holiday, 201);
	}

	public function update(Request $request, Holiday $holiday): JsonResponse
	{
		$data = $request->validate([
			'holidayDate' => ['sometimes', 'date'],
			'name' => ['sometimes', 'string', 'max:255'],
		]);

		if (empty($data)) {
			return response()->json(['message' => 'No data to update'], 422);
		}

		DB::transaction(function () use ($data, $holiday) {
			$holiday->update($data);
		});

		return response()->json($holiday);
	}

	public function destroy(Request $request, Holiday $holiday): JsonResponse
	{
		$holiday->delete();

		return response()->json(null, 204);
	}
}
