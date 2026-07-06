<?php

namespace App\Http\Controllers\Api\V1\Inspection;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionOrderResource;
use App\Models\CheckpointResponse;
use App\Models\DefectRecord;
use App\Models\InspectionEvidence;
use App\Models\InspectionItem;
use App\Models\InspectionOrder;
use App\Models\InspectionTemplate;
use App\Models\InspectionType;
use App\Services\AqlService;
use App\Services\ApprovalService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InspectionOrderController extends Controller
{
    public function __construct(
        private AqlService      $aqlService,
        private ApprovalService $approvalService,
    ) {}

    // ── Orders ────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/inspections
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $user  = $request->user();
        $query = InspectionOrder::with(['template.inspectionType', 'assignedTo', 'company'])->latest();

        if (! $user->hasPermission('inspection.view-all')) {
            $query->where('assigned_to', $user->id);
        } else {
            $query->where('company_id', $user->company_id);
        }

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('assigned_to'))  $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('date_from'))    $query->whereDate('inspection_date', '>=', $request->date_from);
        if ($request->filled('date_to'))      $query->whereDate('inspection_date', '<=', $request->date_to);
        if ($request->filled('search'))       $query->where(fn ($q) =>
            $q->where('po_number', 'ilike', "%{$request->search}%")
              ->orWhere('customer', 'ilike', "%{$request->search}%")
              ->orWhere('product_name', 'ilike', "%{$request->search}%")
        );

        return InspectionOrderResource::collection(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * POST /api/v1/inspections
     * Buat inspection order baru dari template.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'       => ['required', 'uuid', 'exists:inspection_templates,id'],
            'assigned_to'       => ['required', 'uuid', 'exists:users,id'],
            'po_number'         => ['nullable', 'string', 'max:100'],
            'customer'          => ['nullable', 'string', 'max:255'],
            'manufacturer'      => ['nullable', 'string', 'max:255'],
            'product_name'      => ['nullable', 'string', 'max:255'],
            'inspection_date'   => ['nullable', 'date'],
            'location'          => ['nullable', 'string', 'max:255'],
            'sampling_method'   => ['nullable', 'in:aql_2_5,check_all,custom'],
            'inspection_level'  => ['nullable', 'in:I,II,III'],
            'custom_sample_pct' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'notes'             => ['nullable', 'string'],
        ]);

        // Pastikan template published
        $template = InspectionTemplate::findOrFail($data['template_id']);
        if (! $template->is_published) {
            return response()->json(['message' => 'Template belum dipublish.'], 422);
        }

        $user  = $request->user();
        $order = InspectionOrder::create([
            ...$data,
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'status'     => 'draft',
        ]);

        AuditService::log('inspection.created', 'inspection', $order->id, InspectionOrder::class);

        return response()->json([
            'message' => 'Inspeksi berhasil dibuat.',
            'order'   => new InspectionOrderResource($order->load(['template.inspectionType', 'assignedTo', 'company'])),
        ], 201);
    }

    /**
     * GET /api/v1/inspections/{order}
     */
    public function show(InspectionOrder $inspection): JsonResponse
    {
        $this->authorize('view', $inspection);

        return response()->json([
            'order' => new InspectionOrderResource(
                $inspection->load([
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
     * PUT /api/v1/inspections/{order}
     */
    public function update(Request $request, InspectionOrder $order): JsonResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'po_number'       => ['nullable', 'string', 'max:100'],
            'customer'        => ['nullable', 'string', 'max:255'],
            'manufacturer'    => ['nullable', 'string', 'max:255'],
            'product_name'    => ['nullable', 'string', 'max:255'],
            'inspection_date' => ['nullable', 'date'],
            'location'        => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string'],
        ]);

        $order->update($data);
        AuditService::log('inspection.updated', 'inspection', $order->id, InspectionOrder::class);

        return response()->json([
            'message' => 'Inspeksi berhasil diperbarui.',
            'order'   => new InspectionOrderResource($order),
        ]);
    }

    /**
     * DELETE /api/v1/inspections/{order}
     */
    public function destroy(InspectionOrder $order): JsonResponse
    {
        $this->authorize('delete', $order);

        if (! $order->canBeEdited()) {
            return response()->json(['message' => 'Hanya inspeksi berstatus draft/in_progress yang dapat dihapus.'], 422);
        }

        AuditService::log('inspection.deleted', 'inspection', $order->id, InspectionOrder::class);
        $order->delete();

        return response()->json(['message' => 'Inspeksi berhasil dihapus.']);
    }

    /**
     * POST /api/v1/inspections/{order}/submit
     * Submit → trigger approval workflow.
     */
    public function submit(InspectionOrder $order): JsonResponse
    {
        $this->authorize('submit', $order);

        if (! $order->canBeSubmitted()) {
            return response()->json(['message' => 'Inspeksi tidak dapat disubmit pada status saat ini.'], 422);
        }

        if ($order->items()->doesntExist()) {
            return response()->json(['message' => 'Tambahkan minimal satu item produk sebelum submit.'], 422);
        }

        $order->update(['submitted_at' => now(), 'status' => 'submitted']);

        $this->approvalService->initiate($order);

        AuditService::log('inspection.submitted', 'inspection', $order->id, InspectionOrder::class);

        return response()->json([
            'message' => 'Inspeksi berhasil disubmit dan menunggu review.',
            'order'   => new InspectionOrderResource($order->fresh(['approvals.approver'])),
        ]);
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/inspections/{order}/items
     */
    public function listItems(InspectionOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'items' => $order->items()->with(['responses', 'defects', 'evidences'])->get(),
        ]);
    }

    /**
     * POST /api/v1/inspections/{order}/items
     * Tambah item produk ke inspeksi + auto-hitung AQL.
     */
    public function storeItem(Request $request, InspectionOrder $inspection): JsonResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'item_no'        => ['nullable', 'integer', 'min:1'],
            'product_name'   => ['required', 'string', 'max:255'],
            'product_code'   => ['nullable', 'string', 'max:100'],
            'drawing_number' => ['nullable', 'string', 'max:100'],
            'dimensions'     => ['nullable', 'string', 'max:100'],
            'produced_qty'   => ['required', 'integer', 'min:1'],
        ]);

        $itemNo = $data['item_no'] ?? ($inspection->items()->max('item_no') + 1 ?? 1);

        $item = $inspection->items()->create([
            ...$data,
            'item_no'  => $itemNo,
            'result'   => 'pending',
        ]);

        // Auto-calculate AQL sample size
        $this->aqlService->applyToItem($item);

        AuditService::log('inspection.item_added', 'inspection', $inspection->id, InspectionOrder::class,
            ['item_no' => $itemNo, 'product' => $item->product_name]
        );

        return response()->json([
            'message' => 'Item berhasil ditambahkan. Sample size dihitung otomatis.',
            'item'    => $item->fresh(),
        ], 201);
    }

    /**
     * PUT /api/v1/inspections/{order}/items/{item}
     */
    public function updateItem(Request $request, InspectionOrder $order, InspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'product_name'      => ['sometimes', 'string', 'max:255'],
            'product_code'      => ['nullable', 'string', 'max:100'],
            'drawing_number'    => ['nullable', 'string', 'max:100'],
            'dimensions'        => ['nullable', 'string', 'max:100'],
            'produced_qty'      => ['sometimes', 'integer', 'min:1'],
            'inspector_remarks' => ['nullable', 'string'],
        ]);

        $item->update($data);

        // Recalculate AQL if produced_qty changed
        if (isset($data['produced_qty'])) {
            $this->aqlService->applyToItem($item);
        }

        return response()->json(['message' => 'Item berhasil diperbarui.', 'item' => $item->fresh()]);
    }

    /**
     * DELETE /api/v1/inspections/{order}/items/{item}
     */
    public function destroyItem(InspectionOrder $order, InspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);
        $item->delete();

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    // ── Checkpoint Responses ──────────────────────────────────────────────────

    /**
     * POST /api/v1/inspections/{order}/items/{item}/responses
     * Bulk upsert checkpoint responses untuk satu item (semua sample).
     */
    public function saveResponses(Request $request, InspectionOrder $order, InspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        $request->validate([
            'responses'                     => ['required', 'array'],
            'responses.*.checkpoint_id'     => ['required', 'uuid', 'exists:template_checkpoints,id'],
            'responses.*.sample_no'         => ['required', 'integer', 'min:1'],
            'responses.*.value_text'        => ['nullable', 'string'],
            'responses.*.value_number'      => ['nullable', 'numeric'],
            'responses.*.value_unit'        => ['nullable', 'in:mm,inches,cm,degrees,pcs,percent,none'],
            'responses.*.notes'             => ['nullable', 'string'],
        ]);

        $standardUnit = $order->template->default_unit;

        DB::transaction(function () use ($request, $item, $standardUnit) {
            foreach ($request->responses as $res) {
                $checkpoint = \App\Models\TemplateCheckpoint::find($res['checkpoint_id']);
                if (! $checkpoint) continue;

                $validation = ['is_out_of_range' => false, 'is_pass' => null, 'value_converted' => null];

                // Auto-validate numeric measurement checkpoints
                if ($checkpoint->input_type === 'number' && isset($res['value_number'])) {
                    $inputUnit = $res['value_unit'] ?? $checkpoint->unit;
                    $validation = AqlService::validateResponse(
                        $checkpoint,
                        (float) $res['value_number'],
                        $inputUnit,
                        $standardUnit
                    );
                }

                CheckpointResponse::updateOrCreate(
                    [
                        'inspection_item_id' => $item->id,
                        'checkpoint_id'      => $res['checkpoint_id'],
                        'sample_no'          => $res['sample_no'],
                    ],
                    [
                        'value_text'      => $res['value_text'] ?? null,
                        'value_number'    => $res['value_number'] ?? null,
                        'value_unit'      => $res['value_unit'] ?? null,
                        'value_converted' => $validation['value_converted'],
                        'is_out_of_range' => $validation['is_out_of_range'],
                        'is_pass'         => $validation['is_pass'],
                        'notes'           => $res['notes'] ?? null,
                    ]
                );
            }
        });

        // Update order status to in_progress if still draft
        if ($order->isDraft()) {
            $order->update(['status' => 'in_progress']);
        }

        return response()->json(['message' => 'Respons berhasil disimpan.']);
    }

    // ── Defect Records ────────────────────────────────────────────────────────

    /**
     * POST /api/v1/inspections/{order}/items/{item}/defects
     */
    public function storeDefect(Request $request, InspectionOrder $order, InspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'checkpoint_id' => ['nullable', 'uuid', 'exists:template_checkpoints,id'],
            'sample_no'     => ['required', 'integer', 'min:1'],
            'defect_type'   => ['required', 'string', 'max:255'],
            'severity'      => ['required', 'in:minor,major,critical'],
            'qty'           => ['required', 'integer', 'min:1'],
            'action'        => ['nullable', 'in:repair,reject,accept'],
            'remarks'       => ['nullable', 'string'],
        ]);

        $defect = $item->defects()->create($data);

        // Recalculate item result
        $item->recalculateResult();

        return response()->json([
            'message' => 'Defect berhasil dicatat.',
            'defect'  => $defect,
            'item'    => $item->fresh(),
        ], 201);
    }

    /**
     * DELETE /api/v1/defects/{defect}
     */
    public function destroyDefect(DefectRecord $defect): JsonResponse
    {
        $item = $defect->inspectionItem;
        $defect->delete();
        $item->recalculateResult();

        return response()->json(['message' => 'Defect berhasil dihapus.']);
    }

    // ── Evidences ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/inspections/{order}/items/{item}/evidences
     * Upload foto bukti inspeksi.
     */
    public function storeEvidence(Request $request, InspectionOrder $order, InspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        $request->validate([
            'file'          => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'checkpoint_id' => ['nullable', 'uuid', 'exists:template_checkpoints,id'],
            'caption'       => ['nullable', 'string', 'max:255'],
            'order_index'   => ['nullable', 'integer', 'min:0'],
        ]);

        $file     = $request->file('file');
        $fileType = in_array($file->extension(), ['pdf']) ? 'document' : 'photo';
        $path     = $file->store("inspections/{$order->id}/items/{$item->id}", 'public');

        $evidence = $item->evidences()->create([
            'checkpoint_id' => $request->checkpoint_id,
            'caption'       => $request->caption,
            'file_url'      => Storage::url($path),
            'file_type'     => $fileType,
            'taken_at'      => now(),
            'order_index'   => $request->order_index ?? $item->evidences()->count(),
        ]);

        return response()->json([
            'message'  => 'Foto bukti berhasil diupload.',
            'evidence' => $evidence,
        ], 201);
    }

    /**
     * DELETE /api/v1/evidences/{evidence}
     */
    public function destroyEvidence(InspectionEvidence $evidence): JsonResponse
    {
        // Delete file from storage
        $path = str_replace('/storage/', '', $evidence->file_url);
        Storage::disk('public')->delete($path);

        $evidence->delete();

        return response()->json(['message' => 'Bukti berhasil dihapus.']);
    }

    /**
     * GET /api/v1/inspections/{order}/aql-preview
     * Preview AQL calculation untuk semua item dalam order.
     */
    public function aqlPreview(InspectionOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        $items = $order->items()->get()->map(fn ($item) => [
            'item_no'        => $item->item_no,
            'product_name'   => $item->product_name,
            'produced_qty'   => $item->produced_qty,
            'inspected_qty'  => $item->inspected_qty,
            'inspected_pct'  => $item->inspected_pct,
            'aql_accept_no'  => $item->aql_accept_no,
            'aql_reject_no'  => $item->aql_reject_no,
            'result'         => $item->result,
            'minor_defects'  => $item->total_minor_defects,
            'major_defects'  => $item->total_major_defects,
            'critical_defects' => $item->total_critical_defects,
        ]);

        return response()->json([
            'sampling_method'  => $order->effectiveSamplingMethod(),
            'inspection_level' => $order->effectiveInspectionLevel(),
            'items'            => $items,
            'overall_result'   => $items->contains('result', 'fail') ? 'fail' : 'pass',
        ]);
    }

    public function inspectionsType(): JsonResponse
    {
        $types = InspectionType::all();
        
        return response()->json([
            'types' => $types
        ]);
    }
}