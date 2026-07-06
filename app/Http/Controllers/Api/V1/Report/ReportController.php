<?php

namespace App\Http\Controllers\Api\V1\Report;

use App\Exports\InspectionExport;
use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports
     * Filter inspeksi untuk tampilan laporan.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user  = $request->user();
        $query = Inspection::with(['assignedTo', 'template', 'company'])->latest();

        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('date_from'))   $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('search'))      $query->where('title', 'ilike', "%{$request->search}%");

        return response()->json(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    /**
     * POST /api/v1/reports/export/pdf
     * Export PDF untuk satu inspeksi.
     *
     * Body: { "inspection_id": "uuid" }
     */
    public function exportPdf(Request $request): Response
    {
        $request->validate([
            'inspection_id' => ['required', 'uuid', 'exists:inspections,id'],
        ]);

        $inspection = Inspection::with([
            'template', 'assignedTo', 'company',
            'responses.formItem', 'approvals.approver',
        ])->findOrFail($request->inspection_id);

        $this->authorize('view', $inspection);

        $pdf = Pdf::loadView('reports.inspection-pdf', compact('inspection'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
            ]);

        $filename = 'inspeksi-' . str($inspection->title)->slug() . '-' . now()->format('Ymd') . '.pdf';

        AuditService::log('report.export_pdf', 'report', $inspection->id, Inspection::class);

        return $pdf->download($filename);
    }

    /**
     * POST /api/v1/reports/export/excel
     * Export Excel untuk banyak inspeksi (bulk).
     *
     * Body: { "status": "approved", "date_from": "2026-01-01", "date_to": "2026-04-30" }
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'status'    => ['nullable', 'in:draft,submitted,in_review,approved,rejected'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user  = $request->user();
        $query = Inspection::with(['assignedTo', 'template'])->latest();

        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);

        $inspections = $query->get();

        $filename = 'laporan-inspeksi-' . now()->format('Ymd-His') . '.xlsx';

        AuditService::log('report.export_excel', 'report', null, null, [
            'total'  => $inspections->count(),
            'filter' => $request->only(['status', 'date_from', 'date_to']),
        ]);

        return Excel::download(new InspectionExport($inspections), $filename);
    }
}