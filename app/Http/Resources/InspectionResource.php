<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'po_number'    => $this->po_number,
            'customer'     => $this->customer,
            'manufacturer' => $this->manufacturer,
            'product_name' => $this->product_name,
            'inspection_date' => $this->inspection_date?->toISOString(),
            'inspection_level' => $this->inspection_level,
            'custom_sample_pct' => $this->custom_sample_pct,
            'location'     => $this->location,
            'notes'        => $this->notes,
            'status'       => $this->status,
            // 'scheduled_at' => $this->scheduled_at?->toISOString(),
            // 'submitted_at' => $this->submitted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),

            'template' => $this->whenLoaded('template', fn () => [
                'id'    => $this->template->id,
                'name' => $this->template->name,
            ]),

            'assigned_to' => $this->whenLoaded('assignedTo', fn () => [
                'id'   => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ]),

            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),

            'company' => $this->whenLoaded('company', fn () => [
                'id'   => $this->company->id,
                'name' => $this->company->name,
            ]),

            'responses' => $this->whenLoaded('responses', fn () =>
                $this->responses->map(fn ($r) => [
                    'id'          => $r->id,
                    'form_item_id'=> $r->form_item_id,
                    'value'       => $r->value,
                    'media_urls'  => $r->media_urls ?? [],
                    'notes'       => $r->notes,
                ])
            ),

            'approvals' => $this->whenLoaded('approvals', fn () =>
                $this->approvals->map(fn ($a) => [
                    'id'         => $a->id,
                    'step_order' => $a->step_order,
                    'status'     => $a->status,
                    'remarks'    => $a->remarks,
                    'acted_at'   => $a->acted_at?->toISOString(),
                    'approver'   => [
                        'id'   => $a->approver->id,
                        'name' => $a->approver->name,
                    ],
                ])
            ),

            'current_approval_step' => $this->whenLoaded('approvals', fn () =>
                $this->currentApprovalStep()?->step_order
            ),
        ];
    }
}