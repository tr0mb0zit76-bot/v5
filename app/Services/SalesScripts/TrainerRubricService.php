<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Models\SalesScriptPlaySession;
use Illuminate\Support\Str;

class TrainerRubricService
{
    /**
     * @return array{key:string,label:string,description:string,criteria:list<string>,evaluated_criteria:list<array{key:string,label:string,status:string,evidence:string}>,passed_count:int,total_count:int,rubric_score:int}
     */
    public function forSession(SalesScriptPlaySession $session): array
    {
        $session->loadMissing([
            'version.script',
            'events.node',
            'fieldValues.captureField',
            'trainerMessages',
        ]);

        $key = $this->resolveKey($session);
        $rubric = $this->rubrics()[$key] ?? $this->rubrics()['discovery'];
        $evaluated = $this->evaluate($key, $session);
        $passed = collect($evaluated)->where('status', 'passed')->count();
        $pending = collect($evaluated)->where('status', 'pending')->count();
        $total = count($evaluated);

        return [
            ...$rubric,
            'evaluated_criteria' => $evaluated,
            'passed_count' => $passed,
            'total_count' => $total,
            'rubric_score' => $total > 0 ? (int) round((($passed + ($pending * 0.25)) / $total) * 100) : 0,
        ];
    }

    /**
     * @return array<string, array{key:string,label:string,description:string,criteria:list<string>}>
     */
    public function rubrics(): array
    {
        return [
            'price' => [
                'key' => 'price',
                'label' => 'Цена и маржа',
                'description' => 'Проверяет, умеет ли менеджер не отдавать скидку без обмена условий.',
                'criteria' => [
                    'Выяснил, с чем именно сравнивают цену.',
                    'Разложил ставку на условия, риски и сервис.',
                    'Предложил уступку только за встречное обязательство.',
                    'Зафиксировал целевую ставку, дедлайн и следующий шаг.',
                ],
            ],
            'documents' => [
                'key' => 'documents',
                'label' => 'Документы и регламент',
                'description' => 'Проверяет работу с формальными требованиями, КП, закрывающими и чек-листами.',
                'criteria' => [
                    'Собрал обязательный пакет документов и критерии допуска.',
                    'Назвал ответственных и сроки предоставления.',
                    'Не заменил регламент общей презентацией.',
                    'Зафиксировал канал отправки и дедлайн.',
                ],
            ],
            'conflict' => [
                'key' => 'conflict',
                'label' => 'Конфликт и удержание',
                'description' => 'Проверяет работу с претензией, задержкой, компенсацией и восстановлением доверия.',
                'criteria' => [
                    'Признал проблему без спора и оправданий.',
                    'Собрал факты: причина, риск, срок, ответственный.',
                    'Назвал план восстановления и время следующего апдейта.',
                    'Показал, как процесс изменится, чтобы не повторить ошибку.',
                ],
            ],
            'upsell' => [
                'key' => 'upsell',
                'label' => 'Повторная продажа',
                'description' => 'Проверяет расширение действующего клиента без давления и общих презентаций.',
                'criteria' => [
                    'Нашёл конкретную точку расширения: маршрут, объём, пик или риск.',
                    'Связал предложение с KPI клиента.',
                    'Согласовал пилот, условия оплаты и дату ревью.',
                    'Обновил портрет клиента и следующий шаг.',
                ],
            ],
            'discovery' => [
                'key' => 'discovery',
                'label' => 'Квалификация и следующий шаг',
                'description' => 'Проверяет базовую структуру разговора: рамка, вопросы, фиксация и CRM.',
                'criteria' => [
                    'Получил право на короткую диагностику.',
                    'Собрал маршрут, груз, дату, ЛПР и критерии выбора.',
                    'Не назвал ставку без вводных.',
                    'Завершил разговор конкретным следующим шагом.',
                ],
            ],
        ];
    }

    private function resolveKey(SalesScriptPlaySession $session): string
    {
        $script = $session->version?->script;
        $haystack = Str::lower(implode(' ', array_filter([
            $script?->title,
            $script?->description,
            implode(' ', $script?->tags ?? []),
            $session->trainer_profile_key,
            $session->trainer_profile_title,
        ])));

        foreach ([
            'conflict' => ['конфликт', 'претенз', 'удерж', 'service-recovery', 'angry', 'срыв', 'задерж'],
            'price' => ['цена', 'марж', 'ставк', 'конкурент', 'price', 'negotiator'],
            'documents' => ['документ', 'закуп', 'тендер', 'formal', 'регламент'],
            'upsell' => ['апсейл', 'повтор', 'расшир', 'growth', 'действующ'],
        ] as $key => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $key;
                }
            }
        }

        return 'discovery';
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluate(string $key, SalesScriptPlaySession $session): array
    {
        return match ($key) {
            'price' => $this->evaluatePrice($session),
            'documents' => $this->evaluateDocuments($session),
            'conflict' => $this->evaluateConflict($session),
            'upsell' => $this->evaluateUpsell($session),
            default => $this->evaluateDiscovery($session),
        };
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluateDiscovery(SalesScriptPlaySession $session): array
    {
        return [
            $this->criterion(
                'permission',
                'Получил право на короткую диагностику.',
                $this->visitedNodesCount($session) >= 2 || $this->managerMessagesCount($session) >= 2,
                'Есть переход по графу или минимум две реплики менеджера.',
                $session,
            ),
            $this->criterion(
                'qualification',
                'Собрал маршрут, груз, дату, ЛПР и критерии выбора.',
                $this->filledAny($session, ['route_from', 'route_to', 'routes', 'cargo_type', 'loading_date', 'decision_maker', 'client_name', 'decision_criteria'], 3),
                'Проверяются заполненные поля маршрута, груза, даты, ЛПР и критериев.',
                $session,
            ),
            $this->criterion(
                'no_rate_before_inputs',
                'Не назвал ставку без вводных.',
                ! $this->hasNegativeTag($session, ['bad_wrong_stage', 'bad_missed_objection'])
                    && (! $this->filledAny($session, ['target_rate', 'budget_window'], 1) || $this->filledAny($session, ['route_from', 'route_to', 'routes', 'cargo_type', 'loading_date', 'decision_criteria'], 2)),
                'Нет негативной причины “не тот этап / мимо возражения”, ставка не зафиксирована без базовых вводных.',
                $session,
            ),
            $this->criterion(
                'next_step',
                'Завершил разговор конкретным следующим шагом.',
                $this->filledAny($session, ['next_step_date', 'email'], 1) || in_array((string) ($session->outcome?->value ?? $session->outcome), ['won', 'quote_sent', 'progress'], true),
                'Проверяется дата следующего шага, канал КП или продуктивный исход.',
                $session,
            ),
        ];
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluatePrice(SalesScriptPlaySession $session): array
    {
        return [
            $this->criterion('price_compare', 'Выяснил, с чем именно сравнивают цену.', $this->filledAny($session, ['decision_criteria', 'current_provider', 'budget_window'], 2), 'Поля: критерий, конкурент/текущий подрядчик, бюджет.', $session),
            $this->criterion('rate_structure', 'Разложил ставку на условия, риски и сервис.', $this->visitedNodeLike($session, ['price', 'rate', 'trainer_price']) || $this->hasPositiveTag($session, ['useful_objection', 'useful_wording']), 'Проверяются посещённые price/rate-узлы и позитивные причины оценки.', $session),
            $this->criterion('exchange_for_discount', 'Предложил уступку только за встречное обязательство.', $this->filledAny($session, ['payment_terms', 'volume_forecast', 'target_rate'], 2), 'Проверяются условия оплаты, объём и целевая ставка.', $session),
            $this->criterion('price_next_step', 'Зафиксировал целевую ставку, дедлайн и следующий шаг.', $this->filledAny($session, ['target_rate', 'budget_window'], 1) && $this->filledAny($session, ['next_step_date', 'email'], 1), 'Проверяется ставка/бюджет плюс дата или канал следующего шага.', $session),
        ];
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluateDocuments(SalesScriptPlaySession $session): array
    {
        return [
            $this->criterion('docs_package', 'Собрал обязательный пакет документов и критерии допуска.', $this->filledAny($session, ['decision_criteria'], 1) || $this->visitedNodeLike($session, ['document', 'procedure', 'criteria']), 'Проверяются критерии допуска и узлы документов/процедуры.', $session),
            $this->criterion('docs_responsible', 'Назвал ответственных и сроки предоставления.', $this->filledAny($session, ['decision_maker', 'next_step_date'], 2), 'Проверяются ответственный и срок.', $session),
            $this->criterion('not_generic', 'Не заменил регламент общей презентацией.', ! $this->hasNegativeTag($session, ['bad_too_generic', 'bad_not_actionable']), 'Нет негативных причин “слишком общо / нет действия”.', $session),
            $this->criterion('docs_channel', 'Зафиксировал канал отправки и дедлайн.', $this->filledAny($session, ['email', 'next_step_date'], 2), 'Проверяются email/канал и дата.', $session),
        ];
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluateConflict(SalesScriptPlaySession $session): array
    {
        return [
            $this->criterion('conflict_ack', 'Признал проблему без спора и оправданий.', ! $this->hasNegativeTag($session, ['bad_missed_objection', 'bad_too_generic']), 'Нет признаков, что ответ ушёл мимо претензии или был общим.', $session),
            $this->criterion('conflict_facts', 'Собрал факты: причина, риск, срок, ответственный.', $this->filledAny($session, ['decision_criteria', 'decision_maker', 'next_step_date'], 2), 'Проверяются причина/критерий, ответственный и срок.', $session),
            $this->criterion('conflict_plan', 'Назвал план восстановления и время следующего апдейта.', $this->filledAny($session, ['next_step_date'], 1) && ! $this->hasNegativeTag($session, ['bad_not_actionable']), 'Есть дата следующего шага и нет причины “нет действия”.', $session),
            $this->criterion('conflict_prevent', 'Показал, как процесс изменится, чтобы не повторить ошибку.', $this->filledAny($session, ['decision_criteria'], 1) || $this->hasPositiveTag($session, ['useful_next_step']), 'Проверяется критерий предотвращения или позитивный тег следующего шага.', $session),
        ];
    }

    /**
     * @return list<array{key:string,label:string,status:string,evidence:string}>
     */
    private function evaluateUpsell(SalesScriptPlaySession $session): array
    {
        return [
            $this->criterion('growth_point', 'Нашёл конкретную точку расширения: маршрут, объём, пик или риск.', $this->filledAny($session, ['routes', 'route_from', 'route_to', 'volume_forecast', 'decision_criteria'], 2), 'Проверяются маршруты, объём и критерий роста.', $session),
            $this->criterion('growth_kpi', 'Связал предложение с KPI клиента.', $this->filledAny($session, ['decision_criteria'], 1) || $this->hasPositiveTag($session, ['useful_objection', 'useful_wording']), 'Проверяется критерий успеха или позитивная оценка формулировки.', $session),
            $this->criterion('pilot_terms', 'Согласовал пилот, условия оплаты и дату ревью.', $this->filledAny($session, ['volume_forecast', 'payment_terms', 'next_step_date'], 2), 'Проверяются объём, условия оплаты и дата ревью.', $session),
            $this->criterion('portrait_next_step', 'Обновил портрет клиента и следующий шаг.', $this->filledAny($session, ['next_step_date', 'decision_maker'], 1) && in_array((string) ($session->outcome?->value ?? $session->outcome), ['won', 'quote_sent', 'progress'], true), 'Проверяется следующий шаг/ЛПР и продуктивный исход.', $session),
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,evidence:string}
     */
    private function criterion(string $key, string $label, bool $passed, string $evidence, SalesScriptPlaySession $session): array
    {
        if (! $session->isComplete() && ! $this->hasDialogueStarted($session)) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => 'pending',
                'evidence' => $evidence,
            ];
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'passed' : ($session->isComplete() ? 'failed' : 'pending'),
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    private function filledAny(SalesScriptPlaySession $session, array $codes, int $minimum): bool
    {
        $filled = $session->fieldValues
            ->filter(fn ($value): bool => in_array((string) $value->captureField?->code, $codes, true) && trim((string) $value->value) !== '')
            ->pluck('sales_script_capture_field_id')
            ->unique()
            ->count();

        return $filled >= $minimum;
    }

    private function visitedNodesCount(SalesScriptPlaySession $session): int
    {
        return $session->events
            ->where('type', SalesPlayEventType::EnteredNode)
            ->pluck('sales_script_node_id')
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * @param  list<string>  $needles
     */
    private function visitedNodeLike(SalesScriptPlaySession $session, array $needles): bool
    {
        return $session->events
            ->where('type', SalesPlayEventType::EnteredNode)
            ->contains(function ($event) use ($needles): bool {
                $haystack = Str::lower((string) ($event->node?->client_key ?? '').' '.(string) ($event->node?->body ?? ''));

                foreach ($needles as $needle) {
                    if (str_contains($haystack, Str::lower($needle))) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function managerMessagesCount(SalesScriptPlaySession $session): int
    {
        return $session->trainerMessages
            ->where('role', 'user')
            ->count();
    }

    private function hasDialogueStarted(SalesScriptPlaySession $session): bool
    {
        return $this->managerMessagesCount($session) > 0;
    }

    /**
     * @param  list<string>  $tags
     */
    private function hasNegativeTag(SalesScriptPlaySession $session, array $tags): bool
    {
        return $this->hasFeedbackTag($session, $tags, ['negative']);
    }

    /**
     * @param  list<string>  $tags
     */
    private function hasPositiveTag(SalesScriptPlaySession $session, array $tags): bool
    {
        return $this->hasFeedbackTag($session, $tags, ['positive', 'neutral']);
    }

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $reactions
     */
    private function hasFeedbackTag(SalesScriptPlaySession $session, array $tags, array $reactions): bool
    {
        return $session->trainerMessages
            ->contains(function ($message) use ($tags, $reactions): bool {
                $reaction = $message->peer_reaction?->value;
                if (! in_array((string) $reaction, $reactions, true)) {
                    return false;
                }

                foreach ((array) ($message->feedback_tags ?? []) as $tag) {
                    if (is_string($tag) && in_array($tag, $tags, true)) {
                        return true;
                    }
                }

                return false;
            });
    }
}
