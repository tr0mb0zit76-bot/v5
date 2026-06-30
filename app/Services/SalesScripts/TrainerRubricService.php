<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptPlaySession;
use Illuminate\Support\Str;

class TrainerRubricService
{
    /**
     * @return array{key:string,label:string,description:string,criteria:list<string>}
     */
    public function forSession(SalesScriptPlaySession $session): array
    {
        $session->loadMissing('version.script');

        $key = $this->resolveKey($session);

        return $this->rubrics()[$key] ?? $this->rubrics()['discovery'];
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
}
