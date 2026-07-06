<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;

class ApprovalPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('approval.view');
    }

    public function approve(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->hasPermission('approval.approve')
            && $workflow->approver_id === $user->id
            && $workflow->isPending();
    }

    public function reject(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->hasPermission('approval.reject')
            && $workflow->approver_id === $user->id
            && $workflow->isPending();
    }

    public function revise(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->hasPermission('approval.revise')
            && $workflow->approver_id === $user->id
            && $workflow->isPending();
    }
}