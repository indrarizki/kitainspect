<?php

namespace App\Http\Controllers\Api\V1\FormTemplate;

use App\Http\Controllers\Controller;
use App\Models\FormItem;
use App\Models\FormSection;
use App\Models\FormTemplate;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormTemplateController extends Controller
{
    // ── Templates ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/templates
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = FormTemplate::with(['createdBy', 'company'])->latest();

        // Non-admin hanya lihat yang published
        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('is_published', true);
        } else {
            $query->where('company_id', $user->company_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('title', 'ilike', "%{$request->search}%");
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * POST /api/v1/templates
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // 'title'       => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'inspection_type_id' => ['required'],
            'description' => ['nullable', 'string'],
            'category'    => ['nullable', 'string', 'max:100'],
            'schema'      => ['nullable', 'array'],   // form builder JSON
        ]);

        $user     = $request->user();
        $template = FormTemplate::create([
            ...$data,
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'status'     => 'draft',
            'is_published' => false,
        ]);

        AuditService::log('template.created', 'template', $template->id, FormTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil dibuat.',
            'template' => $template,
        ], 201);
    }

    /**
     * GET /api/v1/templates/{template}
     */
    public function show(FormTemplate $template): JsonResponse
    {
        return response()->json([
            'template' => $template->load(['sections.items', 'createdBy', 'company']),
        ]);
    }

    /**
     * PUT /api/v1/templates/{template}
     */
    public function update(Request $request, FormTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category'    => ['nullable', 'string', 'max:100'],
            'schema'      => ['nullable', 'array'],
        ]);

        $template->update($data);
        AuditService::log('template.updated', 'template', $template->id, FormTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil diperbarui.',
            'template' => $template,
        ]);
    }

    /**
     * DELETE /api/v1/templates/{template}
     */
    public function destroy(FormTemplate $template): JsonResponse
    {
        if ($template->inspections()->exists()) {
            return response()->json([
                'message' => 'Template tidak dapat dihapus karena sudah digunakan oleh inspeksi.',
            ], 422);
        }

        AuditService::log('template.deleted', 'template', $template->id, FormTemplate::class);
        $template->delete();

        return response()->json(['message' => 'Template berhasil dihapus.']);
    }

    /**
     * POST /api/v1/templates/{template}/publish
     */
    public function publish(FormTemplate $template): JsonResponse
    {
        $isPublished = ! $template->is_published;

        $template->update([
            'is_published' => $isPublished,
            'status'       => $isPublished ? 'published' : 'draft',
        ]);

        AuditService::log(
            $isPublished ? 'template.published' : 'template.unpublished',
            'template', $template->id, FormTemplate::class
        );

        return response()->json([
            'message'  => $isPublished ? 'Template dipublish.' : 'Template di-unpublish.',
            'template' => $template,
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/duplicate
     */
    public function duplicate(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        $newTemplate = $template->replicate();
        $newTemplate->title        = $template->title . ' (Salinan)';
        $newTemplate->status       = 'draft';
        $newTemplate->is_published = false;
        $newTemplate->created_by   = $user->id;
        $newTemplate->save();

        // Duplicate sections and items
        foreach ($template->sections as $section) {
            $newSection = $section->replicate();
            $newSection->template_id = $newTemplate->id;
            $newSection->save();

            foreach ($section->items as $item) {
                $newItem = $item->replicate();
                $newItem->section_id = $newSection->id;
                $newItem->save();
            }
        }

        AuditService::log('template.duplicated', 'template', $newTemplate->id, FormTemplate::class);

        return response()->json([
            'message'  => 'Template berhasil diduplikasi.',
            'template' => $newTemplate->load('sections.items'),
        ], 201);
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/templates/{template}/sections
     */
    public function sections(FormTemplate $template): JsonResponse
    {
        return response()->json([
            'sections' => $template->sections()->with('items')->get(),
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/sections
     */
    public function storeSection(Request $request, FormTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'code'        => ['nullable', 'string', 'max:50'],
            'title'       => ['required', 'string', 'max:255'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $section = $template->sections()->create([
            'code'        => $data['code'],
            'title'       => $data['title'],
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
    public function updateSection(Request $request, FormSection $section): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $section->update($data);

        return response()->json([
            'message' => 'Section berhasil diperbarui.',
            'section' => $section,
        ]);
    }

    /**
     * DELETE /api/v1/sections/{section}
     */
    public function destroySection(FormSection $section): JsonResponse
    {
        $section->delete();
        return response()->json(['message' => 'Section berhasil dihapus.']);
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/sections/{section}/items
     */
    public function storeItem(Request $request, FormSection $section): JsonResponse
    {
        $data = $request->validate([
            'allow_unit_conversion' => ['boolean'],
            'unit' => ['nullable', 'string', 'max:20'],
            'target_value' => ['nullable', 'numeric'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'severity' => ['nullable', 'in:minor,major,critical'],
            'has_photo' => ['boolean'],
            'description' => ['nullable', 'string'],
            'label'       => ['required', 'string', 'max:255'],
            'input_type'  => ['required', 'in:text,textarea,number,date,checkbox,radio,select,photo,signature,rating'],
            'options'     => ['nullable', 'array'],
            'is_required' => ['boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = $section->items()->create([
            ...$data,
            'order_index' => $data['order_index'] ?? $section->items()->count(),
        ]);

        return response()->json([
            'message' => 'Field berhasil ditambahkan.',
            'item'    => $item,
        ], 201);
    }

    /**
     * PUT /api/v1/items/{item}
     */
    public function updateItem(Request $request, FormItem $item): JsonResponse
    {
        $data = $request->validate([
            'allow_unit_conversion' => ['boolean'],
            'unit' => ['nullable', 'string', 'max:20'],
            'target_value' => ['nullable', 'numeric'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'severity' => ['nullable', 'in:minor,major,critical'],
            'has_photo' => ['boolean'],
            'description' => ['nullable', 'string'],
            'label'       => ['required', 'string', 'max:255'],
            'input_type'  => ['required', 'in:text,textarea,number,date,checkbox,radio,select,photo,signature,rating'],
            'options'     => ['nullable', 'array'],
            'is_required' => ['boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $item->update($data);

        return response()->json([
            'message' => 'Field berhasil diperbarui.',
            'item'    => $item,
        ]);
    }

    /**
     * DELETE /api/v1/items/{item}
     */
    public function destroyItem(FormItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(['message' => 'Field berhasil dihapus.']);
    }
}