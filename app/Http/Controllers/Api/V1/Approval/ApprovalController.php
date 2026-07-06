<?php

namespace App\Http\Controllers\Api\V1\Approval;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionOrderResource;
use App\Models\ApprovalWorkflow;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvalService) {}

    /**
     * GET /api/v1/approvals
     * Antrian approval milik user yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = ApprovalWorkflow::with([
            'inspection.assignedTo',
            'inspection.template.inspectionType',
            'inspection.items',
        ])
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at');

        if ($request->filled('search')) {
            $query->whereHas('inspection', fn ($q) =>
                $q->where('po_number', 'ilike', "%{$request->search}%")
                  ->orWhere('product_name', 'ilike', "%{$request->search}%")
                  ->orWhere('customer', 'ilike', "%{$request->search}%")
            );
        }

        $items = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $items->map(fn ($w) => [
                'id'          => $w->id,
                'step_order'  => $w->step_order,
                'step_label'  => $w->step_order === 1 ? 'Reviewer / QC' : 'Admin Final',
                'status'      => $w->status,
                'created_at'  => $w->created_at->toISOString(),
                'inspection'  => [
                    'id'              => $w->inspection->id,
                    'po_number'       => $w->inspection->po_number,
                    'customer'        => $w->inspection->customer,
                    'manufacturer'    => $w->inspection->manufacturer,
                    'product_name'    => $w->inspection->product_name,
                    'inspection_date' => $w->inspection->inspection_date?->toDateString(),
                    'status'          => $w->inspection->status,
                    'submitted_at'    => $w->inspection->submitted_at?->toISOString(),
                    'total_items'     => $w->inspection->items->count(),
                    'items_summary'   => $w->inspection->items->map(fn ($i) => [
                        'item_no'       => $i->item_no,
                        'product_name'  => $i->product_name,
                        'produced_qty'  => $i->produced_qty,
                        'inspected_qty' => $i->inspected_qty,
                        'result'        => $i->result,
                    ]),
                    'inspection_type' => $w->inspection->template?->inspectionType?->name,
                    'assigned_to'     => $w->inspection->assignedTo?->name,
                ],
            ]),
            'meta' => [
                'total'        => $items->total(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/approvals/{approval}
     * Detail satu approval beserta full inspection data.
     */
    public function show(ApprovalWorkflow $approval): JsonResponse
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        return response()->json([
            'approval' => [
                'id'         => $approval->id,
                'step_order' => $approval->step_order,
                'step_label' => $approval->step_order === 1 ? 'Reviewer / QC' : 'Admin Final',
                'status'     => $approval->status,
                'remarks'    => $approval->remarks,
                'acted_at'   => $approval->acted_at?->toISOString(),
            ],
            'inspection' => new InspectionOrderResource(
                $approval->inspection->load([
                    'template.inspectionType',
                    'template.sections.checkpoints',
                    'assignedTo', 'createdBy', 'company',
                    'items.responses', 'items.defects', 'items.evidences',
                    'approvals.approver',
                ])
            ),
        ]);
    }

    /**
     * POST /api/v1/approvals/{approval}/approve
     */
    public function approve(Request $request, ApprovalWorkflow $approval): JsonResponse
    {
        $this->authorize('approve', $approval);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->approvalService->approve($approval, $request->remarks);

        $msg = $approval->step_order === 1
            ? 'Disetujui QC. Menunggu persetujuan final Admin.'
            : 'Inspeksi disetujui secara final.';

        return response()->json(['message' => $msg]);
    }

    /**
     * POST /api/v1/approvals/{approval}/reject
     */
    public function reject(Request $request, ApprovalWorkflow $approval): JsonResponse
    {
        $this->authorize('reject', $approval);

        $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ], [
            'remarks.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $this->approvalService->reject($approval, $request->remarks);

        return response()->json(['message' => 'Inspeksi ditolak. Inspector akan mendapat notifikasi.']);
    }

    /**
     * POST /api/v1/approvals/{approval}/revise
     */
    public function revise(Request $request, ApprovalWorkflow $approval): JsonResponse
    {
        $this->authorize('revise', $approval);

        $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ], [
            'remarks.required' => 'Catatan revisi wajib diisi.',
        ]);

        $this->approvalService->requestRevision($approval, $request->remarks);

        return response()->json(['message' => 'Permintaan revisi dikirim ke inspector.']);
    }

    /**
     * GET /api/v1/approvals/history
     * Riwayat semua approval yang sudah diproses oleh user.
     */
    public function history(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = ApprovalWorkflow::with(['inspection'])
            ->where('approver_id', $user->id)
            ->whereIn('status', ['approved', 'rejected', 'revised'])
            ->latest('acted_at');

        $items = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $items->map(fn ($w) => [
                'id'         => $w->id,
                'step_order' => $w->step_order,
                'status'     => $w->status,
                'remarks'    => $w->remarks,
                'acted_at'   => $w->acted_at?->toISOString(),
                'inspection' => [
                    'id'          => $w->inspection->id,
                    'po_number'   => $w->inspection->po_number,
                    'customer'    => $w->inspection->customer,
                    'product_name'=> $w->inspection->product_name,
                    'status'      => $w->inspection->status,
                ],
            ]),
            'meta' => [
                'total'        => $items->total(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
            ],
        ]);
    }
}