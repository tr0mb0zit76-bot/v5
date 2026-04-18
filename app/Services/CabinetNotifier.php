<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\CabinetInAppNotification;
use Illuminate\Support\Facades\Schema;

class CabinetNotifier
{
    public function notifyDocumentApprovalRequested(Order $order, OrderDocument $document, User $requester): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'supervisor']))
            ->where('id', '!=', $requester->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $orderLabel = $order->order_number !== null && $order->order_number !== ''
            ? (string) $order->order_number
            : '#'.$order->id;

        $docName = $document->original_name !== null && $document->original_name !== ''
            ? $document->original_name
            : 'заявка';

        $title = 'Согласование заявки';
        $body = sprintf(
            '%s отправил(а) на согласование документ «%s» по заказу %s.',
            $requester->name,
            $docName,
            $orderLabel
        );

        $actionUrl = route('orders.edit', $order, false).'?tab=documents';

        $notification = new CabinetInAppNotification(
            'order_document_approval',
            $title,
            $body,
            $actionUrl,
            [
                'order_id' => $order->id,
                'order_document_id' => $document->id,
            ]
        );

        foreach ($recipients as $user) {
            $user->notify($notification);
        }
    }

    public function notifyTaskAssigned(Task $task, ?User $actor): void
    {
        if (! Schema::hasTable('notifications') || $actor === null) {
            return;
        }

        $responsibleId = $task->responsible_id;
        if ($responsibleId === null || (int) $responsibleId === (int) $actor->id) {
            return;
        }

        $recipient = User::query()->where('is_active', true)->find($responsibleId);
        if ($recipient === null) {
            return;
        }

        $title = 'Новая задача';
        $body = sprintf(
            '%s назначил(а) вам задачу №%s — %s.',
            $actor->name,
            $task->number,
            $task->title
        );

        $actionUrl = route('tasks.index', absolute: false).'?task='.$task->id;

        $recipient->notify(new CabinetInAppNotification(
            'task_assigned',
            $title,
            $body,
            $actionUrl,
            ['task_id' => $task->id],
        ));
    }

    public function notifyTaskComment(Task $task, TaskComment $comment, ?User $author): void
    {
        if (! Schema::hasTable('notifications') || $author === null) {
            return;
        }

        $responsibleId = $task->responsible_id;
        if ($responsibleId === null || (int) $responsibleId === (int) $author->id) {
            return;
        }

        $recipient = User::query()->where('is_active', true)->find($responsibleId);
        if ($recipient === null) {
            return;
        }

        $title = 'Комментарий к задаче';
        $body = sprintf(
            '%s в задаче №%s: %s',
            $author->name,
            $task->number,
            mb_strimwidth((string) $comment->body, 0, 160, '…')
        );

        $actionUrl = route('tasks.index', absolute: false).'?task='.$task->id;

        $recipient->notify(new CabinetInAppNotification(
            'task_comment',
            $title,
            $body,
            $actionUrl,
            ['task_id' => $task->id, 'task_comment_id' => $comment->id],
        ));
    }

    public function notifyTaskSlaBreached(Task $task): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = User::query()
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $task->responsible_id !== null,
                fn ($query) => $query->where('id', '!=', $task->responsible_id)
            )
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'supervisor']))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Просрочен SLA по задаче';
        $body = sprintf(
            'Задача №%s — %s: контрольный срок SLA истёк.',
            $task->number,
            $task->title
        );

        $actionUrl = route('tasks.show', $task, false);

        $notification = new CabinetInAppNotification(
            'task_sla_breached',
            $title,
            $body,
            $actionUrl,
            ['task_id' => $task->id],
        );

        foreach ($recipients as $user) {
            $user->notify($notification);
        }
    }
}
