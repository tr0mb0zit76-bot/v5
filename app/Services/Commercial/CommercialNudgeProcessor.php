<?php

namespace App\Services\Commercial;

class CommercialNudgeProcessor
{
    public function __construct(
        private readonly CommercialNudgeEvaluator $evaluator,
        private readonly CommercialNudgeTaskService $taskService,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function process(): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->evaluator->collectAll() as $match) {
            if ($this->taskService->hasOpenTask($match)) {
                $skipped++;

                continue;
            }

            $this->taskService->createFromMatch($match);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
