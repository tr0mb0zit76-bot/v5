<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderStatusLog;
use App\Models\Task;
use App\Support\ActivityEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderActivityTimelineService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function timelineForOrder(Order $order, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        $events = collect()
            ->merge($this->activityLedger->timelineForSubject($order, $limit * 3))
            ->merge($this->statusLogEvents($order))
            ->merge($this->taskEvents($order))
            ->merge($this->documentEvents($order));

        return $events
            ->sort(function (array $left, array $right): int {
                $leftAt = (string) ($left['occurred_at'] ?? '');
                $rightAt = (string) ($right['occurred_at'] ?? '');

                if ($leftAt !== $rightAt) {
                    return strcmp($rightAt, $leftAt);
                }

                return strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function statusLogEvents(Order $order): Collection
    {
        if (! Schema::hasTable('order_status_logs')) {
            return collect();
        }

        return OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (OrderStatusLog $log): array {
                $from = $log->status_from ? $this->orderStatusService->label((string) $log->status_from) : null;
                $to = $this->orderStatusService->label((string) $log->status_to);
                $summary = $from !== null ? "{$from} → {$to}" : $to;

                if (filled($log->comment)) {
                    $summary .= '. '.$log->comment;
                }

                return [
                    'id' => 'status_log_'.$log->id,
                    'event_type' => 'order_status_changed',
                    'event_label' => 'Статус заказа',
                    'title' => $to,
                    'summary' => $summary,
                    'payload' => [
                        'status_from' => $log->status_from,
                        'status_to' => $log->status_to,
                    ],
                    'occurred_at' => $log->created_at?->toIso8601String(),
                    'user_name' => $log->creator?->name,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function taskEvents(Order $order): Collection
    {
        if (! Schema::hasTable('tasks')) {
            return collect();
        }

        return Task::query()
            ->where('order_id', $order->id)
            ->with('responsible:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => 'task_'.$task->id,
                'event_type' => ActivityEventType::TaskCreated->value,
                'event_label' => ActivityEventType::TaskCreated->label(),
                'title' => $task->title,
                'summary' => filled($task->description) ? (string) $task->description : null,
                'payload' => [
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'priority' => $task->priority,
                ],
                'occurred_at' => $task->created_at?->toIso8601String(),
                'user_name' => $task->responsible?->name,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function documentEvents(Order $order): Collection
    {
        if (! Schema::hasTable('order_documents')) {
            return collect();
        }

        return OrderDocument::query()
            ->where('order_id', $order->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->flatMap(function (OrderDocument $document): array {
                $events = [];
                $title = $document->original_name ?: ($document->number ?: $document->type);

                if ($document->created_at !== null) {
                    $events[] = $this->documentEvent(
                        $document,
                        'order_document_added',
                        'Документ',
                        'Добавлен: '.$title,
                        $document->created_at,
                    );
                }

                if ($document->signed_at !== null) {
                    $events[] = $this->documentEvent(
                        $document,
                        'order_document_signed',
                        'Документ',
                        'Подписан: '.$title,
                        $document->signed_at,
                    );
                }

                if ($document->approved_at !== null) {
                    $events[] = $this->documentEvent(
                        $document,
                        'order_document_approved',
                        'Документ',
                        'Согласован: '.$title,
                        $document->approved_at,
                    );
                }

                return $events;
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentEvent(
        OrderDocument $document,
        string $eventType,
        string $eventLabel,
        string $summary,
        Carbon $occurredAt,
    ): array {
        return [
            'id' => $eventType.'_'.$document->id.'_'.$occurredAt->timestamp,
            'event_type' => $eventType,
            'event_label' => $eventLabel,
            'title' => $document->type,
            'summary' => $summary,
            'payload' => [
                'document_id' => $document->id,
                'type' => $document->type,
                'workflow_status' => $document->workflow_status,
            ],
            'occurred_at' => $occurredAt->toIso8601String(),
            'user_name' => null,
        ];
    }
}
