<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Super admin can see all companies
        if ($user->isSuperAdmin()) {
            $companies = Company::all();
        } else {
            // Admin hanya bisa lihat company-nya sendiri
            $companies = $user->company()->where('id', $user->company_id)->latest()->get();
        }

        return response()->json($companies);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        // Super admin can see any company
        if ($user->isSuperAdmin()) {
            $company = $user->company()->findOrFail($id);
        } else {
            // Admin hanya bisa lihat company-nya sendiri
            $company = $user->company()->where('id', $id)->firstOrFail();
        }

        return response()->json($company);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        // Super admin can update any company
        if ($user->isSuperAdmin()) {
            $company = $user->company()->findOrFail($id);
        } else {
            // Admin hanya bisa update company-nya sendiri
            $company = $user->company()->where('id', $id)->firstOrFail();
        }

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $company->update($data);

        return response()->json($company);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // Super admin can delete any company
        if ($user->isSuperAdmin()) {
            $company = $user->company()->findOrFail($id);
        } else {
            // Admin hanya bisa delete company-nya sendiri
            $company = $user->company()->where('id', $id)->firstOrFail();
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully.']);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membuat company.'], 403);
        }

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'code'      => ['required', 'string', 'max:50', 'unique:companies,code'],
        ]);

        $company = $user->company()->create($data);

        return response()->json($company, 201);
    }   
}
