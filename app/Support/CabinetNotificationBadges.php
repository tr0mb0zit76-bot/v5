<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class CabinetNotificationBadges
{
    /**
     * @return array{total: int, orders: int, tasks: int}
     */
    public static function unreadFor(User $user): array
    {
        if (! Schema::hasTable('notifications')) {
            return [
                'total' => 0,
                'orders' => 0,
                'tasks' => 0,
            ];
        }

        return [
            'total' => $user->unreadNotifications()->count(),
            'orders' => $user->unreadNotifications()->where('data->kind', 'order_document_approval')->count(),
            'tasks' => $user->unreadNotifications()
                ->where(function ($query): void {
                    $query->where('data->kind', 'task_assigned')
                        ->orWhere('data->kind', 'task_comment');
                })
                ->count(),
        ];
    }
}
