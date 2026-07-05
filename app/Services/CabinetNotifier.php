<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\ContractorRiskAssessment;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\CabinetInAppNotification;
use App\Services\Contractor\ContractorLimitApprovalService;
use App\Services\Mobile\MobilePushService;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Support\Facades\Schema;

class CabinetNotifier
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MobilePushService $mobilePushService,
    ) {}

    public function notifyDocumentApprovalRequested(Order $order, OrderDocument $document, User $requester): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = $this->recipientResolver
            ->approvalRecipientsForOrder($order, $requester);

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

        $actionUrl = route('orders.documents.preview-draft', [$order, $document], false);

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
            $this->deliver($user, $notification);
        }
    }

    /**
     * Уведомление о согласовании заявки: менеджеру (владельцу заказа) и инициатору отправки на согласование (если это другие люди, не подписант).
     */
    public function notifyDocumentApproved(Order $order, OrderDocument $document, User $signer): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipientIds = [];

        if ($order->manager_id !== null) {
            $recipientIds[] = (int) $order->manager_id;
        }

        if (Schema::hasColumn('order_documents', 'approval_requested_by') && $document->approval_requested_by !== null) {
            $recipientIds[] = (int) $document->approval_requested_by;
        }

        $signerId = (int) $signer->id;
        $recipientIds = array_values(array_unique(array_filter(
            $recipientIds,
            static fn (int $id): bool => $id > 0 && $id !== $signerId,
        )));

        if ($recipientIds === []) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('id', $recipientIds)
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

        $title = 'Заявка подписана';
        $body = sprintf(
            '%s согласовал(а) документ «%s» по заказу %s.',
            $signer->name,
            $docName,
            $orderLabel
        );

        $actionUrl = route('orders.edit', [$order], false);

        $notification = new CabinetInAppNotification(
            'order_document_approved',
            $title,
            $body,
            $actionUrl,
            [
                'order_id' => $order->id,
                'order_document_id' => $document->id,
            ]
        );

        foreach ($recipients as $user) {
            $this->deliver($user, $notification);
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

        $this->deliver($recipient, new CabinetInAppNotification(
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

        $this->deliver($recipient, new CabinetInAppNotification(
            'task_comment',
            $title,
            $body,
            $actionUrl,
            ['task_id' => $task->id, 'task_comment_id' => $comment->id],
        ));
    }

    public function notifyChatMessage(ChatMessage $message, User $author): void
    {
        $message->loadMissing(['conversation.participants', 'recipient']);
        $conversation = $message->conversation;

        if ($conversation === null) {
            return;
        }

        if (Schema::hasTable('conversation_participants')) {
            $recipients = $conversation->participants
                ->filter(fn (User $participant): bool => (int) $participant->id !== (int) $author->id);

            if ($message->recipient_user_id !== null) {
                $recipients = $recipients
                    ->filter(fn (User $participant): bool => (int) $participant->id === (int) $message->recipient_user_id);
            }

            if ($recipients->isNotEmpty()) {
                $conversationTitle = $conversation->type === 'group'
                    ? ($conversation->title ?: 'Групповой чат')
                    : $author->name;
                $body = sprintf('%s: %s', $author->name, mb_strimwidth((string) $message->body, 0, 160, '…'));
                $actionUrl = '/?messenger_conversation='.$conversation->id;

                $this->mobilePushService->notifyUsers(
                    $recipients,
                    'chat_message',
                    $conversationTitle,
                    $body,
                    [
                        'action_url' => $actionUrl,
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'author_id' => $author->id,
                        'recipient_user_id' => $message->recipient_user_id,
                    ],
                );
            }
        }
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
            $this->deliver($user, $notification);
        }
    }

    public function notifyContractorLimitApprovalRequested(
        Contractor $contractor,
        ContractorRiskAssessment $assessment,
        User $requester,
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = $this->recipientResolver
            ->approvalRecipientsForUser($requester, $requester);

        if ($recipients->isEmpty()) {
            return;
        }

        $approvalService = app(ContractorLimitApprovalService::class);
        $reasonLabel = $approvalService->reasonLabel($assessment->submission_reason);

        $title = 'Согласование лимита контрагента';
        $body = sprintf(
            '%s отправил(а) на согласование «%s» (%s). Причина: %s.',
            $requester->name,
            $contractor->name,
            $contractor->inn ?? 'без ИНН',
            $reasonLabel,
        );

        $actionUrl = route('contractors.show', [$contractor], false);

        $notification = new CabinetInAppNotification(
            'contractor_limit_approval',
            $title,
            $body,
            $actionUrl,
            [
                'contractor_id' => $contractor->id,
                'contractor_risk_assessment_id' => $assessment->id,
                'submission_reason' => $assessment->submission_reason,
            ],
        );

        foreach ($recipients as $user) {
            $this->deliver($user, $notification);
        }
    }

    public function notifyContractorPrintFormChangeRequested(
        Contractor $contractor,
        ContractorPrintFormChangeRequest $changeRequest,
        User $requester,
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = $this->recipientResolver
            ->approvalRecipientsForUser($requester, $requester);

        if ($recipients->isEmpty()) {
            return;
        }

        $partyLabel = $changeRequest->party === 'carrier' ? 'перевозчика' : 'заказчика';

        $title = 'Согласование базовых условий';
        $body = sprintf(
            '%s отправил(а) на согласование условия %s для «%s».',
            $requester->name,
            $partyLabel,
            $contractor->name,
        );

        $actionUrl = route('contractors.show', [
            'contractor' => $contractor->id,
            'tab' => 'cooperation',
            'print_party' => $changeRequest->party,
        ], false);

        $notification = new CabinetInAppNotification(
            'contractor_print_form_change',
            $title,
            $body,
            $actionUrl,
            [
                'contractor_id' => $contractor->id,
                'contractor_print_form_change_request_id' => $changeRequest->id,
                'party' => $changeRequest->party,
            ],
        );

        foreach ($recipients as $user) {
            $this->deliver($user, $notification);
        }
    }

    private function deliver(User $user, CabinetInAppNotification $notification): void
    {
        $user->notify($notification);
        $this->mobilePushService->notifyCabinetNotification($user, $notification);
    }
}
