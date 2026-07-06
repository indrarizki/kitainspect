<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ─── InspectionTemplateResource ───────────────────────────────────────────────
class InspectionTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'description'      => $this->description,
            'version'          => $this->version,
            'sampling_method'  => $this->sampling_method,
            'inspection_level' => $this->inspection_level,
            'default_unit'     => $this->default_unit,
            'status'           => $this->status,
            'is_published'     => $this->is_published,
            'created_at'       => $this->created_at?->toISOString(),

            'inspection_type' => $this->whenLoaded('inspectionType', fn () => [
                'id'   => $this->inspectionType->id,
                'name' => $this->inspectionType->name,
                'slug' => $this->inspectionType->slug,
            ]),

            'company' => $this->whenLoaded('company', fn () => [
                'id'   => $this->company->id,
                'name' => $this->company->name,
            ]),

            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),

            'sections' => $this->whenLoaded('sections', fn () =>
                $this->sections->map(fn ($section) => [
                    'id'           => $section->id,
                    'code'         => $section->code,
                    'title'        => $section->title,
                    'section_type' => $section->section_type,
                    'order_index'  => $section->order_index,
                    'is_required'  => $section->is_required,
                    'checkpoints'  => $section->relationLoaded('checkpoints')
                        ? $section->checkpoints->map(fn ($cp) => [
                            'id'                    => $cp->id,
                            'label'                 => $cp->label,
                            'input_type'            => $cp->input_type,
                            'unit'                  => $cp->unit,
                            'allow_unit_conversion' => $cp->allow_unit_conversion,
                            'target_value'          => $cp->target_value,
                            'min_value'             => $cp->min_value,
                            'max_value'             => $cp->max_value,
                            'severity'              => $cp->severity,
                            'is_required'           => $cp->is_required,
                            'has_photo'             => $cp->has_photo,
                            'options'               => $cp->options,
                            'description'           => $cp->description,
                            'order_index'           => $cp->order_index,
                        ])
                        : [],
                ])
            ),
        ];
    }
}