<?php

namespace App\Services\SalesScripts;

use App\Contracts\Inference\ChatCompletionClient;
use App\Enums\TrainerAiRole;
use App\Models\SalesScriptPlaySession;
use App\Models\User;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\AiInteractionOutcome;

class TrainerChatCompletionService
{
    public function __construct(
        private readonly ChatCompletionClient $chatCompletionClient,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
        private readonly TrainerSalesBookBriefService $trainerSalesBookBriefService,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array{role:string,content:string,at?:string}>  $history
     * @param  array<string, mixed>  $playPresentation
     * @return array{reply: string, outcome: AiInteractionOutcome}
     */
    public function replyForTrainerSession(
        SalesScriptPlaySession $session,
        array $profile,
        array $history,
        string $lastUserMessage,
        ?User $user,
        array $playPresentation,
    ): array {
        if (! $this->chatCompletionClient->isAvailable()) {
            return [
                'reply' => 'Не настроен DEEPSEEK_API_KEY. Пока тренировка недоступна.',
                'outcome' => AiInteractionOutcome::Unavailable,
            ];
        }

        $session->loadMissing('version.script');
        $managerAsBuyer = $session->training_role_mode === 'manager_buyer';
        $aiRole = $managerAsBuyer ? TrainerAiRole::Seller : TrainerAiRole::Client;

        $systemPrompt = $this->buildSystemPrompt(
            $session,
            $profile,
            $playPresentation,
            $managerAsBuyer,
            $history,
            $lastUserMessage,
            $user,
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach (array_slice($history, -20) as $item) {
            $messages[] = [
                'role' => $item['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($item['content'] ?? ''),
            ];
        }

        try {
            $messages = $this->sanitizer->sanitizeMessages($messages, 'trainer');
            $content = trim($this->chatCompletionClient->chat($messages, [
                'temperature' => $managerAsBuyer ? 0.72 : 0.8,
                'max_tokens' => $managerAsBuyer ? 450 : 350,
            ]));

            if ($content !== '') {
                return [
                    'reply' => $content,
                    'outcome' => AiInteractionOutcome::Success,
                ];
            }

            return [
                'reply' => $this->fallbackReply($session, $managerAsBuyer),
                'outcome' => AiInteractionOutcome::WeakAnswer,
            ];
        } catch (\Throwable) {
            return [
                'reply' => $managerAsBuyer
                    ? 'Сейчас не удалось получить ответ. Повторите сообщение или попробуйте ещё раз позже.'
                    : 'Сейчас не удалось получить ответ клиента. Повторите сообщение еще раз.',
                'outcome' => AiInteractionOutcome::Failed,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array{role:string,content:string,at?:string}>  $history
     * @param  array<string, mixed>  $playPresentation
     */
    private function buildSystemPrompt(
        SalesScriptPlaySession $session,
        array $profile,
        array $playPresentation,
        bool $managerAsBuyer,
        array $history,
        string $lastUserMessage,
        ?User $user,
    ): string {
        $title = (string) ($profile['title'] ?? 'Покупатель');
        $context = (string) ($profile['context'] ?? 'Веди реалистичный диалог как клиент.');
        $profileKey = (string) ($profile['key'] ?? '');
        $scriptTitle = (string) ($session->version?->script?->title ?? 'Скрипт продаж');

        $sharedTrainerSceneRules = "Общие правила тренировочной сцены:\n".
            "- Это одна непрерывная сцена переговоров; не сбрасывай контекст и не веди себя как при новом первом контакте, если диалог уже развёрнут.\n".
            "- Не повторяй дословно одно и то же возражение или вопрос, если на него уже ответили — двигай диалог вперёд.\n".
            "- Если собеседник повторяет вопрос — кратко уточни или дай новый угол, а не копируй предыдущую реплику.\n".
            "- Если в последних репликах уже зафиксированы конкретные договорённости (следующий шаг, срок, сумма, время созвона, явное согласие) — не разворачивай переговоры заново: дай короткий итог или заверши реплику без новых продажных циклов по уже закрытым вопросам.\n".
            "- Если собеседник явно завершает диалог (благодарность и стоп, «на этом достаточно», финальный тон согласия) — поддержи завершение, не уводи в новую воронку.\n";

        $sellerTrainerRules = "Как звучать в диалоге:\n".
            "- Ситуация — живой контакт менеджера с собеседником (часто первый или ранний); ты не знаешь заранее его полномочия и настрой — выясняй естественно.\n".
            "- Ориентир по продукту и отрасли — из названия сценария «{$scriptTitle}»; не выдумывай юридическое название своей компании, если его не назвали в переписке — можно «мы», «наша сторона», «наша компания».\n".
            "- Ни в коем случае не произноси вслух слова «профиль», «тренажёр», «сценарий обучения», не обращайся к собеседнику как к «игроку покупателя».\n".
            "- Ниже дано описание типичного поведения собеседника (для твоего понимания возражений) — это не то, что ты должен ему процитировать или озвучивать.\n";

        $buyerTrainerRules = "Как звучать в диалоге:\n".
            "- Ты обычный собеседник на стороне клиента; ниже — описание твоей роли для отработки (не озвучивай метки «профиль», «тренажёр»).\n";

        $systemPrompt = $managerAsBuyer
            ? "Ты — менеджер по продажам / представитель поставщика в учебном диалоге (письменная имитация звонка или переписки).\n".
                "Собеседник отвечает как представитель заказчика. Ориентир по теме разговора: «{$scriptTitle}».\n\n".
                $sellerTrainerRules.
                "\nТиповый портрет собеседника (внутренняя подсказка, не для цитирования): {$title}.\n".
                "Доп. контекст его роли (внутренняя подсказка): {$context}\n\n".
                "Правила реплик:\n".
                "- Только от лица продавца; реалистично и коротко (1–4 предложения).\n".
                "- Профессионально: уточняй потребность, работай с возражениями, предлагай следующий шаг без токсичности.\n".
                "- Не дави на мгновенное закрытие в первых репликах; если покупатель уже согласился на конкретный шаг — зафиксируй и не откатывай уже решённое.\n".
                "- Не раскрывай, что ты AI.\n\n".
                $sharedTrainerSceneRules
            : "Ты — клиент / заказчик в учебном диалоге.\n".
                "Менеджер (пользователь) тренируется с тобой. Тема сценария: «{$scriptTitle}».\n\n".
                $buyerTrainerRules.
                "\nТвоя роль: {$title}\n".
                "Контекст поведения: {$context}\n\n".
                "Правила реплик:\n".
                "- Только от лица клиента; реалистично и коротко (1–4 предложения).\n".
                "- Иногда задавай встречные вопросы.\n".
                "- Не раскрывай, что ты AI.\n".
                "- Оценивай предложения менеджера как в живом разговоре.\n\n".
                $sharedTrainerSceneRules;

        $graphBlock = $this->graphContextBlock($playPresentation, $managerAsBuyer);
        if ($graphBlock !== '') {
            $systemPrompt .= "\n\n".$graphBlock;
        }

        if (! $managerAsBuyer) {
            $systemPrompt .= "\n\n".$this->clientOpeningRules($profileKey, $history);
        }

        $extra = trim((string) ($session->trainer_assistant_instructions ?? ''));
        if ($extra !== '') {
            $systemPrompt .= "\n\nДополнительные указания к репликам:\n".$extra;
        }

        if ($user !== null) {
            $salesBookBrief = $this->trainerSalesBookBriefService->buildContextBlock(
                $user,
                $scriptTitle,
                $lastUserMessage,
                $history,
            );

            if (is_string($salesBookBrief) && $salesBookBrief !== '') {
                $systemPrompt .= "\n\n".$salesBookBrief;
            }
        }

        return $systemPrompt;
    }

    /**
     * @param  array<string, mixed>  $playPresentation
     */
    private function graphContextBlock(array $playPresentation, bool $managerAsBuyer): string
    {
        if ($managerAsBuyer) {
            return '';
        }

        $stepKey = (string) ($playPresentation['step_key'] ?? '');
        $operatorLine = trim((string) ($playPresentation['operator_line'] ?? ''));
        $branchInstruction = trim((string) ($playPresentation['branch_instruction'] ?? ''));
        $coachingHint = trim((string) ($playPresentation['coaching_hint'] ?? ''));
        $choices = $playPresentation['choices'] ?? [];

        if ($stepKey === '' && $operatorLine === '' && $branchInstruction === '' && $choices === []) {
            return '';
        }

        $lines = ['Текущий шаг сценария (внутренняя подсказка, не цитируй дословно менеджеру):'];
        if ($stepKey !== '') {
            $lines[] = "- Ключ шага: {$stepKey}";
        }
        if ($operatorLine !== '') {
            $lines[] = "- Менеджер сейчас должен был сказать примерно: «{$operatorLine}»";
        }
        if ($branchInstruction !== '') {
            $lines[] = '- Сейчас фаза ветвления: менеджер задал вопросы, твоя реплика — одна из типичных реакций клиента.';
            $lines[] = "- Контекст ветки: {$branchInstruction}";
        }
        if ($coachingHint !== '') {
            $lines[] = "- Подсказка коучинга: {$coachingHint}";
        }

        if (is_array($choices) && $choices !== []) {
            $lines[] = '- Твоя реплика должна соответствовать ОДНОМУ из вариантов реакции клиента на этом шаге:';
            foreach ($choices as $choice) {
                if (! is_array($choice)) {
                    continue;
                }
                $label = trim((string) ($choice['label'] ?? ''));
                if ($label !== '') {
                    $lines[] = "  • {$label}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{role:string,content:string,at?:string}>  $history
     */
    private function clientOpeningRules(string $profileKey, array $history): string
    {
        $assistantTurns = 0;
        foreach ($history as $item) {
            if (($item['role'] ?? '') === 'assistant') {
                $assistantTurns++;
            }
        }

        if ($assistantTurns > 0) {
            return '';
        }

        $base = "Первая реплика клиента в этом диалоге:\n".
            "- Не соглашайся сразу; звучи как реальный занятой собеседник.\n".
            "- Можно коротко уточнить цель звонка или попросить прислать информацию на почту.\n";

        if ($profileKey === 'lpr-skeptic') {
            return $base.
                "- Ты скептичный ЛПР: сдержанный тон, сомнение в необходимости менять перевозчика.\n".
                "- Не говори «да, интересно» в первой реплике; скорее «у нас всё устраивает» или «чем вы лучше текущего?».\n".
                "- Не раскрывай внутренние метрики сразу — пусть менеджер заработает доверие.\n";
        }

        if ($profileKey === 'price-sensitive-owner') {
            return $base.
                "- Сразу обозначь чувствительность к цене: сравниваешь ставки, не готов переплачивать без обоснования.\n";
        }

        if ($profileKey === 'procurement-formal') {
            return $base.
                "- Отвечай сухо и формально: запроси документы, критерии квалификации, без эмоций.\n";
        }

        if ($profileKey === 'hard-price-negotiator') {
            return $base.
                "- Сразу дави на цену и конкурента: назови, что есть предложение дешевле, но не раскрывай все условия сразу.\n".
                "- Не соглашайся на скидку без встречного вопроса: проверяй, предложит ли менеджер обмен уступки на объём, предоплату, регулярность или SLA.\n";
        }

        if ($profileKey === 'service-recovery-angry') {
            return $base.
                "- Начни раздражённо: укажи на задержку, документы или простой и потребуй конкретный план, а не извинения.\n".
                "- Смягчайся только если менеджер признаёт проблему, собирает факты, называет ответственного и время следующего апдейта.\n";
        }

        if ($profileKey === 'existing-client-growth') {
            return $base.
                "- Отвечай как действующий клиент: вы уже работаете, но расширение объёма требует понятной выгоды, KPI пилота и даты ревью.\n".
                "- Не принимай общую презентацию; проси конкретный маршрут, объём, условия оплаты и ответственного.\n";
        }

        return $base;
    }

    private function fallbackReply(SalesScriptPlaySession $session, bool $managerAsBuyer): string
    {
        $scriptTitle = trim((string) ($session->version?->script?->title ?? ''));

        if ($managerAsBuyer) {
            return $scriptTitle !== ''
                ? 'Добрый день! Повторю короче: звоню впервые по теме «'.$scriptTitle.'» — подскажите, удобно ли сейчас пару минут?'
                : 'Добрый день! Повторю короче: звоню впервые — подскажите, удобно ли сейчас пару минут?';
        }

        return 'Клиент задумался и просит уточнить детали.';
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array{role:string,content:string,at?:string}>  $history
     */
    public function reply(
        SalesScriptPlaySession $session,
        array $profile,
        array $history,
        TrainerAiRole $aiRole,
    ): string {
        $result = $this->replyForTrainerSession(
            $session,
            $profile,
            $history,
            (string) (collect($history)->last()['content'] ?? ''),
            null,
            [],
        );

        return $result['reply'];
    }
}
