<?php

namespace App\Http\Controllers\Api\V1\Template;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionTemplateResource;
use App\Models\InspectionTemplate;
use App\Models\InspectionType;
use App\Models\TemplateCheckpoint;
use App\Models\TemplateSection;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionTemplateController extends Controller
{
    // ── Templates ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/templates
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = InspectionTemplate::with(['inspectionType', 'createdBy', 'company'])->latest();

        // Non-admin hanya lihat yang published di company-nya
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('company_id', $user->company_id);
        } else {
            $query->where('company_id', $user->company_id)
                  ->where('is_published', true);
        }

        if ($request->filled('inspection_type')) {
            $query->whereHas('inspectionType', fn ($q) => $q->where('slug', $request->inspection_type));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        return response()->json(
            InspectionTemplateResource::collection(
                $query->paginate($request->integer('per_page', 15))
            )
        );
    }

    /**
     * POST /api/v1/templates
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inspection_type_id' => ['required', 'uuid', 'exists:inspection_types,id'],
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'version'            => ['nullable', 'string', 'max:20'],
            'sampling_method'    => ['required', 'in:aql_2_5,check_all,custom'],
            'custom_sample_pct'  => ['nullable', 'numeric', 'min:1', 'max:100'],
            'inspection_level'   => ['nullable', 'in:I,II,III'],
            'default_unit'       => ['required', 'in:mm,inches,cm'],
        ], [
            'custom_sample_pct.required_if' => 'Persentase custom wajib diisi jika metode custom.',
        ]);

        $user     = $request->user();
        $template = InspectionTemplate::create([
            ...$data,
            'company_id'       => $user->company_id,
            'created_by'       => $user->id,
            'inspection_level' => $data['inspection_level'] ?? 'II',
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        AuditService::log('template.created', 'template', $template->id, InspectionTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil dibuat.',
            'template' => new InspectionTemplateResource($template->load(['inspectionType', 'createdBy'])),
        ], 201);
    }

    /**
     * GET /api/v1/templates/{template}
     */
    public function show(InspectionTemplate $template): JsonResponse
    {
        return response()->json([
            'template' => new InspectionTemplateResource(
                $template->load(['inspectionType', 'company', 'createdBy', 'sections.checkpoints'])
            ),
        ]);
    }

    /**
     * PUT /api/v1/templates/{template}
     */
    public function update(Request $request, InspectionTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'version'           => ['nullable', 'string', 'max:20'],
            'sampling_method'   => ['sometimes', 'in:aql_2_5,check_all,custom'],
            'custom_sample_pct' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'inspection_level'  => ['nullable', 'in:I,II,III'],
            'default_unit'      => ['sometimes', 'in:mm,inches,cm'],
        ]);

        $template->update($data);
        AuditService::log('template.updated', 'template', $template->id, InspectionTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil diperbarui.',
            'template' => new InspectionTemplateResource($template->load(['inspectionType'])),
        ]);
    }

    /**
     * DELETE /api/v1/templates/{template}
     */
    public function destroy(InspectionTemplate $template): JsonResponse
    {
        if ($template->inspectionOrders()->exists()) {
            return response()->json([
                'message' => 'Template tidak dapat dihapus karena sudah digunakan oleh inspeksi.',
            ], 422);
        }

        AuditService::log('template.deleted', 'template', $template->id, InspectionTemplate::class);
        $template->delete();

        return response()->json(['message' => 'Template berhasil dihapus.']);
    }

    /**
     * POST /api/v1/templates/{template}/publish
     */
    public function publish(InspectionTemplate $template): JsonResponse
    {
        // Validasi: harus ada minimal 1 section dengan 1 checkpoint sebelum publish
        if (! $template->is_published) {
            $hasCheckpoints = $template->sections()
                ->whereHas('checkpoints')
                ->exists();

            if (! $hasCheckpoints) {
                return response()->json([
                    'message' => 'Template harus memiliki minimal satu section dan checkpoint sebelum dipublish.',
                ], 422);
            }
        }

        $isPublished = ! $template->is_published;
        $template->update([
            'is_published' => $isPublished,
            'status'       => $isPublished ? 'published' : 'draft',
        ]);

        AuditService::log(
            $isPublished ? 'template.published' : 'template.unpublished',
            'template', $template->id, InspectionTemplate::class
        );

        return response()->json([
            'message'  => $isPublished ? 'Template berhasil dipublish.' : 'Template di-unpublish.',
            'template' => new InspectionTemplateResource($template),
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/duplicate
     */
    public function duplicate(Request $request, InspectionTemplate $template): JsonResponse
    {
        $user        = $request->user();
        $newTemplate = $template->replicate();
        $newTemplate->name         = $template->name . ' (Salinan)';
        $newTemplate->status       = 'draft';
        $newTemplate->is_published = false;
        $newTemplate->created_by   = $user->id;
        $newTemplate->version      = '1.0';
        $newTemplate->save();

        foreach ($template->sections()->with('checkpoints')->get() as $section) {
            $newSection = $section->replicate();
            $newSection->template_id = $newTemplate->id;
            $newSection->save();

            foreach ($section->checkpoints as $cp) {
                $newCp = $cp->replicate();
                $newCp->section_id = $newSection->id;
                $newCp->save();
            }
        }

        AuditService::log('template.duplicated', 'template', $newTemplate->id, InspectionTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil diduplikasi.',
            'template' => new InspectionTemplateResource($newTemplate->load('sections.checkpoints')),
        ], 201);
    }

    /**
     * GET /api/v1/inspection-types
     * List all active inspection types (for dropdown)
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => InspectionType::where('is_active', true)
                ->select('id', 'name', 'slug', 'description')
                ->get(),
        ]);
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/templates/{template}/sections
     */
    public function storeSection(Request $request, InspectionTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'code'         => ['required', 'string', 'max:10'],
            'title'        => ['required', 'string', 'max:255'],
            'section_type' => ['required', 'in:order_detail,measurement,visual,deformation,remarks,evidence,aql_summary,signature,custom'],
            'order_index'  => ['nullable', 'integer', 'min:0'],
            'is_required'  => ['boolean'],
        ]);

        // Check duplicate code in same template
        if ($template->sections()->where('code', $data['code'])->exists()) {
            return response()->json(['message' => "Section dengan kode '{$data['code']}' sudah ada."], 422);
        }

        $section = $template->sections()->create([
            ...$data,
            'order_index' => $data['order_index'] ?? $template->sections()->count(),
        ]);

        return response()->json([
            'message' => 'Section berhasil ditambahkan.',
            'section' => $section,
        ], 201);
    }

    /**
     * PUT /api/v1/sections/{section}
     */
    public function updateSection(Request $request, TemplateSection $section): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['sometimes', 'string', 'max:255'],
            'section_type' => ['sometimes', 'in:order_detail,measurement,visual,deformation,remarks,evidence,aql_summary,signature,custom'],
            'order_index'  => ['nullable', 'integer', 'min:0'],
            'is_required'  => ['boolean'],
        ]);

        $section->update($data);

        return response()->json(['message' => 'Section berhasil diperbarui.', 'section' => $section]);
    }

    /**
     * DELETE /api/v1/sections/{section}
     */
    public function destroySection(TemplateSection $section): JsonResponse
    {
        $section->delete();
        return response()->json(['message' => 'Section berhasil dihapus.']);
    }

    /**
     * PUT /api/v1/sections/reorder — reorder multiple sections at once
     */
    public function reorderSections(Request $request): JsonResponse
    {
        $request->validate([
            'orders'            => ['required', 'array'],
            'orders.*.id'       => ['required', 'uuid', 'exists:template_sections,id'],
            'orders.*.order_index' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->orders as $item) {
            TemplateSection::where('id', $item['id'])->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Urutan section berhasil diperbarui.']);
    }

    // ── Checkpoints ───────────────────────────────────────────────────────────

    /**
     * POST /api/v1/sections/{section}/checkpoints
     */
    public function storeCheckpoint(Request $request, TemplateSection $section): JsonResponse
    {
        $data = $request->validate([
            'label'                 => ['required', 'string', 'max:255'],
            'input_type'            => ['required', 'in:number,text,select,checkbox,photo,signature,rating'],
            'unit'                  => ['nullable', 'in:mm,inches,cm,degrees,pcs,percent,none'],
            'allow_unit_conversion' => ['boolean'],
            'target_value'          => ['nullable', 'numeric'],
            'min_value'             => ['nullable', 'numeric'],
            'max_value'             => ['nullable', 'numeric', 'gte:min_value'],
            'severity'              => ['nullable', 'in:minor,major,critical'],
            'is_required'           => ['boolean'],
            'has_photo'             => ['boolean'],
            'options'               => ['nullable', 'array'],
            'description'           => ['nullable', 'string'],
            'order_index'           => ['nullable', 'integer', 'min:0'],
        ]);

        $checkpoint = $section->checkpoints()->create([
            ...$data,
            'unit'        => $data['unit'] ?? 'none',
            'order_index' => $data['order_index'] ?? $section->checkpoints()->count(),
        ]);

        return response()->json([
            'message'    => 'Checkpoint berhasil ditambahkan.',
            'checkpoint' => $checkpoint,
        ], 201);
    }

    /**
     * PUT /api/v1/checkpoints/{checkpoint}
     */
    public function updateCheckpoint(Request $request, TemplateCheckpoint $checkpoint): JsonResponse
    {
        $data = $request->validate([
            'label'                 => ['sometimes', 'string', 'max:255'],
            'input_type'            => ['sometimes', 'in:number,text,select,checkbox,photo,signature,rating'],
            'unit'                  => ['nullable', 'in:mm,inches,cm,degrees,pcs,percent,none'],
            'allow_unit_conversion' => ['boolean'],
            'target_value'          => ['nullable', 'numeric'],
            'min_value'             => ['nullable', 'numeric'],
            'max_value'             => ['nullable', 'numeric'],
            'severity'              => ['nullable', 'in:minor,major,critical'],
            'is_required'           => ['boolean'],
            'has_photo'             => ['boolean'],
            'options'               => ['nullable', 'array'],
            'description'           => ['nullable', 'string'],
            'order_index'           => ['nullable', 'integer', 'min:0'],
        ]);

        $checkpoint->update($data);

        return response()->json(['message' => 'Checkpoint berhasil diperbarui.', 'checkpoint' => $checkpoint]);
    }

    /**
     * DELETE /api/v1/checkpoints/{checkpoint}
     */
    public function destroyCheckpoint(TemplateCheckpoint $checkpoint): JsonResponse
    {
        $checkpoint->delete();
        return response()->json(['message' => 'Checkpoint berhasil dihapus.']);
    }

    /**
     * PUT /api/v1/checkpoints/reorder
     */
    public function reorderCheckpoints(Request $request): JsonResponse
    {
        $request->validate([
            'orders'               => ['required', 'array'],
            'orders.*.id'          => ['required', 'uuid', 'exists:template_checkpoints,id'],
            'orders.*.order_index' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->orders as $item) {
            TemplateCheckpoint::where('id', $item['id'])->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Urutan checkpoint berhasil diperbarui.']);
    }
}