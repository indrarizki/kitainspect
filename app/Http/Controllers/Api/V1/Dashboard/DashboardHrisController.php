<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class DashboardHrisController extends Controller
{
    /**
     * GET /api/v1/dashboard/hris
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $employeeCount = Employee::where('company_id', $user->company_id)->count();

        $recentHires = Employee::where('company_id', $user->company_id)
            ->orderBy('hired_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'position', 'hired_at']);

        return response()->json([
            'summary'        => [
                'total_employees' => $employeeCount,
            ],
        ]);
    }
}
