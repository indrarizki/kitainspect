<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function send(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $inspectionOrderId = null,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id'              => $user->id,
            'inspection_order_id'  => $inspectionOrderId,
            'type'                 => $type,
            'title'                => $title,
            'body'                 => $body,
            'data'                 => $data,
        ]);
    }

    public static function notifyRole(
        string $roleSlug,
        string $companyId,
        string $type,
        string $title,
        string $body,
        ?string $inspectionOrderId = null,
        array $data = []
    ): void {
        $users = User::whereHas('role', fn ($q) => $q->where('slug', $roleSlug))
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        foreach ($users as $user) {
            self::send($user, $type, $title, $body, $inspectionOrderId, $data);
        }
    }

    public static function notifyUser(
        string $userId,
        string $type,
        string $title,
        string $body,
        ?string $inspectionOrderId = null,
        array $data = []
    ): void {
        $user = User::find($userId);
        if ($user) {
            self::send($user, $type, $title, $body, $inspectionOrderId, $data);
        }
    }
}