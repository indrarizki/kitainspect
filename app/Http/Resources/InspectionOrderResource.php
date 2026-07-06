<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'po_number'       => $this->po_number,
            'customer'        => $this->customer,
            'manufacturer'    => $this->manufacturer,
            'product_name'    => $this->product_name,
            'inspection_date' => $this->inspection_date?->toDateString(),
            'location'        => $this->location,
            'status'          => $this->status,
            'sampling_method' => $this->effectiveSamplingMethod(),
            'inspection_level'=> $this->effectiveInspectionLevel(),
            'notes'           => $this->notes,
            'submitted_at'    => $this->submitted_at?->toISOString(),
            'completed_at'    => $this->completed_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),

            'template' => $this->whenLoaded('template', fn () => [
                'id'           => $this->template->id,
                'name'         => $this->template->name,
                'default_unit' => $this->template->default_unit,
                'inspection_type' => [
                    'name' => $this->template->inspectionType->name ?? null,
                    'slug' => $this->template->inspectionType->slug ?? null,
                ],
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

            'items' => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($item) => [
                    'id'                    => $item->id,
                    'item_no'               => $item->item_no,
                    'product_name'          => $item->product_name,
                    'product_code'          => $item->product_code,
                    'drawing_number'        => $item->drawing_number,
                    'dimensions'            => $item->dimensions,
                    'produced_qty'          => $item->produced_qty,
                    'inspected_qty'         => $item->inspected_qty,
                    'inspected_pct'         => $item->inspected_pct,
                    'aql_accept_no'         => $item->aql_accept_no,
                    'aql_reject_no'         => $item->aql_reject_no,
                    'total_minor_defects'   => $item->total_minor_defects,
                    'total_major_defects'   => $item->total_major_defects,
                    'total_critical_defects'=> $item->total_critical_defects,
                    'result'                => $item->result,
                    'inspector_remarks'     => $item->inspector_remarks,

                    'responses' => $item->relationLoaded('responses')
                        ? $item->responses->groupBy('sample_no')->map(fn ($group) =>
                            $group->map(fn ($r) => [
                                'id'              => $r->id,
                                'checkpoint_id'   => $r->checkpoint_id,
                                'sample_no'       => $r->sample_no,
                                'value_text'      => $r->value_text,
                                'value_number'    => $r->value_number,
                                'value_unit'      => $r->value_unit,
                                'value_converted' => $r->value_converted,
                                'is_out_of_range' => $r->is_out_of_range,
                                'is_pass'         => $r->is_pass,
                                'notes'           => $r->notes,
                            ])
                          )
                        : null,

                    'defects' => $item->relationLoaded('defects')
                        ? $item->defects->map(fn ($d) => [
                            'id'           => $d->id,
                            'checkpoint_id'=> $d->checkpoint_id,
                            'sample_no'    => $d->sample_no,
                            'defect_type'  => $d->defect_type,
                            'severity'     => $d->severity,
                            'qty'          => $d->qty,
                            'action'       => $d->action,
                            'remarks'      => $d->remarks,
                          ])
                        : null,

                    'evidences' => $item->relationLoaded('evidences')
                        ? $item->evidences->map(fn ($e) => [
                            'id'            => $e->id,
                            'checkpoint_id' => $e->checkpoint_id,
                            'caption'       => $e->caption,
                            'file_url'      => $e->file_url,
                            'file_type'     => $e->file_type,
                            'taken_at'      => $e->taken_at?->toISOString(),
                            'order_index'   => $e->order_index,
                          ])
                        : null,
                ])
            ),

            'approvals' => $this->whenLoaded('approvals', fn () =>
                $this->approvals->map(fn ($a) => [
                    'id'         => $a->id,
                    'step_order' => $a->step_order,
                    'status'     => $a->status,
                    'remarks'    => $a->remarks,
                    'acted_at'   => $a->acted_at?->toISOString(),
                    'approver'   => ['id' => $a->approver->id, 'name' => $a->approver->name],
                ])
            ),

            'current_step' => $this->whenLoaded('approvals',
                fn () => $this->currentApprovalStep()?->step_order
            ),
        ];
    }
}