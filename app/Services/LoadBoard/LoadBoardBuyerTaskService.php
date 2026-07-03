<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;
use App\Models\Task;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Support\TaskNumberGenerator;
use Illuminate\Support\Facades\Schema;

class LoadBoardBuyerTaskService
{
    public function __construct(
        private readonly TaskNumberGenerator $taskNumbers,
        private readonly CabinetNotifier $cabinetNotifier,
    ) {}

    public function ensureForPost(LoadBoardPost $post, ?User $actor = null): ?Task
    {
        if (! Schema::hasTable('tasks') || ! in_array($post->priority, ['high', 'urgent'], true)) {
            return null;
        }

        $buyerId = (int) ($post->buyer_id ?? 0);
        if ($buyerId <= 0) {
            return null;
        }

        $existing = Task::query()
            ->where('meta->load_board_post_id', $post->id)
            ->whereIn('status', ['new', 'in_progress', 'review', 'on_hold'])
            ->first();

        if ($existing instanceof Task) {
            return $existing;
        }

        $task = Task::query()->create([
            'number' => $this->taskNumbers->next(),
            'title' => $this->title($post),
            'description' => $this->description($post),
            'status' => 'new',
            'priority' => $post->priority === 'urgent' ? 'critical' : 'high',
            'due_at' => $post->priority === 'urgent' ? now()->endOfDay() : now()->addDay()->endOfDay(),
            'created_by' => $actor?->id ?? $post->seller_id,
            'responsible_id' => $buyerId,
            'lead_id' => $post->lead_id,
            'order_id' => $post->order_id,
            'contractor_id' => $post->customer_id,
            'meta' => [
                'source' => 'load_board',
                'load_board_post_id' => $post->id,
                'load_board_priority' => $post->priority,
                'load_board_status' => $post->status,
                'load_board_url' => route('load-board.index', absolute: false).'?filter=buyer',
            ],
        ]);

        $this->cabinetNotifier->notifyTaskAssigned($task, $actor);

        return $task;
    }

    private function title(LoadBoardPost $post): string
    {
        return sprintf('Биржа грузов: найти перевозчика — %s', $post->title);
    }

    private function description(LoadBoardPost $post): string
    {
        $route = trim(sprintf(
            '%s → %s',
            $post->loading_location ?: 'погрузка не указана',
            $post->unloading_location ?: 'выгрузка не указана',
        ));

        $dates = collect([
            $post->loading_date?->format('d.m.Y'),
            $post->unloading_date?->format('d.m.Y'),
        ])->filter()->implode(' → ');

        $parts = [
            'Срочный груз на внутренней Бирже.',
            'Маршрут: '.$route.'.',
            $dates !== '' ? 'Даты: '.$dates.'.' : null,
            $post->cargo_name ? 'Груз: '.$post->cargo_name.'.' : null,
            $post->target_carrier_rate !== null ? 'Целевая ставка перевозчика: '.$post->target_carrier_rate.' '.$post->customer_rate_currency.'.' : null,
            'Откройте раздел «Биржа грузов», подготовьте ATI-публикацию и добавьте варианты перевозчиков.',
        ];

        return collect($parts)->filter()->implode("\n");
    }
}
