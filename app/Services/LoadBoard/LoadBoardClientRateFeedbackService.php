<?php

declare(strict_types=1);

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;
use App\Models\Task;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Support\TaskNumberGenerator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Продавец сообщает закупщику фактическую ставку, по которой клиент возит по коридору.
 */
class LoadBoardClientRateFeedbackService
{
    public const METADATA_KEY = 'client_rate_feedback';

    public function __construct(
        private readonly CabinetNotifier $cabinetNotifier,
        private readonly TaskNumberGenerator $taskNumbers,
    ) {}

    public function assertCanSubmit(LoadBoardPost $post, User $actor): void
    {
        if ($actor->isAdmin() || $actor->isSupervisor()) {
            return;
        }

        if ((int) $post->seller_id === (int) $actor->id) {
            return;
        }

        abort(403, 'Обратную связь по ставке клиента может оставить продавец груза.');
    }

    /**
     * @param  array{rate: float|int|string, currency?: string|null, note?: string|null}  $input
     * @return array{post: LoadBoardPost, task: ?Task, notified_buyer: bool}
     */
    public function submit(LoadBoardPost $post, User $actor, array $input): array
    {
        $this->assertCanSubmit($post, $actor);

        $rate = round((float) $input['rate'], 2);
        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'rate' => 'Укажите ставку клиента больше нуля.',
            ]);
        }

        $currency = strtoupper((string) ($input['currency'] ?? $post->customer_rate_currency ?: 'RUB'));
        $note = trim((string) ($input['note'] ?? ''));
        $note = $note !== '' ? mb_substr($note, 0, 1000) : null;

        $previousRate = $post->customer_rate !== null ? (float) $post->customer_rate : null;
        $previousCurrency = strtoupper((string) ($post->customer_rate_currency ?: 'RUB'));

        $feedback = [
            'rate' => $rate,
            'currency' => $currency,
            'note' => $note,
            'previous_rate' => $previousRate,
            'previous_currency' => $previousCurrency,
            'reported_by' => (int) $actor->id,
            'reported_by_name' => (string) $actor->name,
            'reported_at' => now()->toIso8601String(),
        ];

        $metadata = is_array($post->metadata) ? $post->metadata : [];
        $metadata[self::METADATA_KEY] = $feedback;

        $post->fill([
            'customer_rate' => $rate,
            'customer_rate_currency' => $currency,
            'metadata' => $metadata,
        ]);
        $post->save();

        $task = $this->ensureBuyerFeedbackTask($post->fresh(), $actor, $feedback);
        $notified = $this->cabinetNotifier->notifyLoadBoardClientRateFeedback($post, $actor, $feedback);

        return [
            'post' => $post->fresh(),
            'task' => $task,
            'notified_buyer' => $notified,
        ];
    }

    /**
     * @param  array<string, mixed>  $feedback
     */
    private function ensureBuyerFeedbackTask(LoadBoardPost $post, User $actor, array $feedback): ?Task
    {
        if (! Schema::hasTable('tasks')) {
            return null;
        }

        $buyerId = (int) ($post->buyer_id ?? 0);
        if ($buyerId <= 0) {
            return null;
        }

        $rateLabel = number_format((float) $feedback['rate'], 2, '.', ' ').' '.$feedback['currency'];
        $noteLine = filled($feedback['note'] ?? null) ? "\nКомментарий: ".$feedback['note'] : '';

        $existing = Task::query()
            ->where('meta->load_board_post_id', $post->id)
            ->where('meta->source', 'load_board_client_rate_feedback')
            ->whereIn('status', ['new', 'in_progress', 'review', 'on_hold'])
            ->first();

        $description = sprintf(
            "%s сообщил(а) фактическую ставку клиента по грузу «%s»: %s.%s\nОткройте кейс на Бирже грузов.",
            $actor->name,
            $post->title,
            $rateLabel,
            $noteLine,
        );

        if ($existing instanceof Task) {
            $existing->update([
                'title' => $this->taskTitle($post, $rateLabel),
                'description' => $description,
                'responsible_id' => $buyerId,
                'meta' => array_merge(is_array($existing->meta) ? $existing->meta : [], [
                    'source' => 'load_board_client_rate_feedback',
                    'load_board_post_id' => $post->id,
                    'client_rate' => $feedback['rate'],
                    'client_rate_currency' => $feedback['currency'],
                    'load_board_url' => route('load-board.cases.show', $post, absolute: false),
                ]),
            ]);

            return $existing->fresh();
        }

        $task = Task::query()->create([
            'number' => $this->taskNumbers->next(),
            'title' => $this->taskTitle($post, $rateLabel),
            'description' => $description,
            'status' => 'new',
            'priority' => 'medium',
            'due_at' => now()->addDay()->endOfDay(),
            'created_by' => $actor->id,
            'responsible_id' => $buyerId,
            'lead_id' => $post->lead_id,
            'order_id' => $post->order_id,
            'contractor_id' => $post->customer_id,
            'meta' => [
                'source' => 'load_board_client_rate_feedback',
                'load_board_post_id' => $post->id,
                'client_rate' => $feedback['rate'],
                'client_rate_currency' => $feedback['currency'],
                'load_board_url' => route('load-board.cases.show', $post, absolute: false),
            ],
        ]);

        $this->cabinetNotifier->notifyTaskAssigned($task, $actor);

        return $task;
    }

    private function taskTitle(LoadBoardPost $post, string $rateLabel): string
    {
        return sprintf('Биржа: ставка клиента %s — %s', $rateLabel, $post->title);
    }
}
