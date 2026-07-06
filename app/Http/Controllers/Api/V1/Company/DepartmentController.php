<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $departments = Department::where('parent_id', $user->company_id)->latest()->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create([
            'parent_id' => $user->company_id,
            'name'       => $data['name'],
        ]);

        return response()->json($department, 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $department = Department::where('parent_id', $user->company_id)->findOrFail($id);

        return response()->json($department);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $department = Department::where('parent_id', $user->company_id)->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->update($data);

        return response()->json($department);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $department = Department::where('parent_id', $user->company_id)->findOrFail($id);

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }
}