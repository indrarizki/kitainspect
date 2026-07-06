<?php

namespace App\Policies;

use App\Models\InspectionOrder;
use App\Models\User;

class InspectionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function view(User $user, InspectionOrder $order): bool
    {
        if ($user->hasPermission('inspection.view-all')) {
            return $user->company_id === $order->company_id;
        }
        return $user->hasPermission('inspection.view-own')
            && $order->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inspection.create');
    }

    public function update(User $user, InspectionOrder $order): bool
    {
        if (! $order->canBeEdited()) return false;
        if ($user->hasPermission('inspection.view-all')) {
            return $user->company_id === $order->company_id;
        }
        return $user->hasPermission('inspection.update-own')
            && $order->assigned_to === $user->id;
    }

    public function delete(User $user, InspectionOrder $order): bool
    {
        return $user->hasPermission('inspection.delete')
            && $user->company_id === $order->company_id;
    }

    public function submit(User $user, InspectionOrder $order): bool
    {
        if (! $order->canBeSubmitted()) return false;
        return $user->hasPermission('inspection.submit')
            && $order->assigned_to === $user->id;
    }
}