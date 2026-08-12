<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\User;

/**
 * Оркестратор «Проверить мост 1С»: health + эскалация.
 */
final class OneCBridgeCheckService
{
    public function __construct(
        private readonly OneCBridgeHealthService $health,
        private readonly OneCBridgeEscalationService $escalation,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     summary_ru: string,
     *     companies: list<array<string, mixed>>,
     *     task_created: ?array{id: int, title: string, number: string}
     * }
     */
    public function check(?User $initiator = null, ?string $companyCode = null): array
    {
        $verdict = $this->health->evaluate($companyCode);
        $escalation = ['created' => false, 'task' => null];

        try {
            $escalation = $this->escalation->escalateFromVerdict($verdict, $initiator);
        } catch (\InvalidArgumentException $e) {
            $verdict['summary_ru'] = $verdict['summary_ru'].' (эскалация не создана: '.$e->getMessage().')';
            if ($verdict['status'] === 'ok') {
                $verdict['status'] = 'attention';
            }
        }

        $task = $escalation['task'];

        return [
            ...$verdict,
            'task_created' => $task === null ? null : [
                'id' => $task->id,
                'title' => $task->title,
                'number' => (string) $task->number,
            ],
        ];
    }
}
