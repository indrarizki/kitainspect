<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\InspectionOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * Called when inspector submits an inspection order.
     * Creates step-1 pending approval for all active reviewers in same company.
     */
    public function initiate(InspectionOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $reviewers = User::whereHas('role', fn ($q) => $q->where('slug', 'reviewer'))
                ->where('company_id', $order->company_id)
                ->where('is_active', true)
                ->get();

            foreach ($reviewers as $reviewer) {
                ApprovalWorkflow::create([
                    'inspection_order_id' => $order->id,
                    'approver_id'         => $reviewer->id,
                    'step_order'          => 1,
                    'status'              => 'pending',
                ]);
            }

            $order->update(['status' => 'in_review']);

            NotificationService::notifyRole(
                roleSlug: 'reviewer',
                companyId: $order->company_id,
                type: 'inspection.submitted',
                title: 'Inspeksi baru menunggu review',
                body: "Inspeksi PO \"{$order->po_number}\" — {$order->product_name} telah disubmit.",
                inspectionOrderId: $order->id,
            );

            AuditService::log('inspection.submitted', 'inspection', $order->id, InspectionOrder::class);
        });
    }

    /**
     * Approve a workflow step.
     * Step 1 (Reviewer) → creates Step 2 for Admin.
     * Step 2 (Admin)    → marks order as approved (final).
     */
    public function approve(ApprovalWorkflow $workflow, ?string $remarks = null): void
    {
        DB::transaction(function () use ($workflow, $remarks) {
            $order = $workflow->inspection;

            $workflow->update([
                'status'   => 'approved',
                'remarks'  => $remarks,
                'acted_at' => now(),
            ]);

            // Cancel other pending workflows on same step (other reviewers)
            ApprovalWorkflow::where('inspection_order_id', $order->id)
                ->where('step_order', $workflow->step_order)
                ->where('status', 'pending')
                ->where('id', '!=', $workflow->id)
                ->update(['status' => 'approved', 'acted_at' => now(), 'remarks' => 'Auto-approved']);

            AuditService::log(
                "approval.step{$workflow->step_order}.approved",
                'approval',
                $order->id,
                InspectionOrder::class,
                ['step' => $workflow->step_order, 'remarks' => $remarks]
            );

            if ($workflow->step_order === 1) {
                // Escalate to step 2 — Admin
                $admins = User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                    ->where('company_id', $order->company_id)
                    ->where('is_active', true)
                    ->get();

                foreach ($admins as $admin) {
                    ApprovalWorkflow::create([
                        'inspection_order_id' => $order->id,
                        'approver_id'         => $admin->id,
                        'step_order'          => 2,
                        'status'              => 'pending',
                    ]);
                }

                NotificationService::notifyRole(
                    roleSlug: 'admin',
                    companyId: $order->company_id,
                    type: 'approval.step2_needed',
                    title: 'Inspeksi perlu persetujuan final',
                    body: "PO \"{$order->po_number}\" telah disetujui QC dan menunggu persetujuan Anda.",
                    inspectionOrderId: $order->id,
                );
            } else {
                // Step 2 final — approved
                $order->update(['status' => 'approved', 'completed_at' => now()]);

                NotificationService::notifyUser(
                    userId: $order->assigned_to,
                    type: 'inspection.approved',
                    title: 'Inspeksi Anda disetujui',
                    body: "Inspeksi PO \"{$order->po_number}\" telah disetujui secara final.",
                    inspectionOrderId: $order->id,
                );

                AuditService::log('inspection.approved', 'inspection', $order->id, InspectionOrder::class);
            }
        });
    }

    /**
     * Reject a workflow step → order back to draft.
     */
    public function reject(ApprovalWorkflow $workflow, string $remarks): void
    {
        DB::transaction(function () use ($workflow, $remarks) {
            $order = $workflow->inspection;

            $workflow->update([
                'status'   => 'rejected',
                'remarks'  => $remarks,
                'acted_at' => now(),
            ]);

            // Cancel all other pending steps
            ApprovalWorkflow::where('inspection_order_id', $order->id)
                ->where('status', 'pending')
                ->where('id', '!=', $workflow->id)
                ->update(['status' => 'rejected', 'acted_at' => now()]);

            $order->update(['status' => 'rejected']);

            NotificationService::notifyUser(
                userId: $order->assigned_to,
                type: 'inspection.rejected',
                title: 'Inspeksi Anda ditolak',
                body: "Inspeksi PO \"{$order->po_number}\" ditolak. Alasan: {$remarks}",
                inspectionOrderId: $order->id,
                data: ['remarks' => $remarks],
            );

            AuditService::log(
                "approval.step{$workflow->step_order}.rejected",
                'approval',
                $order->id,
                InspectionOrder::class,
                ['step' => $workflow->step_order, 'remarks' => $remarks]
            );
        });
    }

    /**
     * Request revision without full rejection → order back to in_progress.
     */
    public function requestRevision(ApprovalWorkflow $workflow, string $remarks): void
    {
        DB::transaction(function () use ($workflow, $remarks) {
            $order = $workflow->inspection;

            $workflow->update([
                'status'   => 'revised',
                'remarks'  => $remarks,
                'acted_at' => now(),
            ]);

            $order->update(['status' => 'in_progress']);

            NotificationService::notifyUser(
                userId: $order->assigned_to,
                type: 'inspection.revision_requested',
                title: 'Revisi diperlukan',
                body: "Inspeksi PO \"{$order->po_number}\" perlu direvisi. Catatan: {$remarks}",
                inspectionOrderId: $order->id,
                data: ['remarks' => $remarks],
            );

            AuditService::log(
                'approval.revision_requested',
                'approval',
                $order->id,
                InspectionOrder::class,
                ['step' => $workflow->step_order, 'remarks' => $remarks]
            );
        });
    }
}