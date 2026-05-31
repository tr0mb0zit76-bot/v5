<?php

namespace App\Services\SalesScripts;

use App\Contracts\Inference\ChatCompletionClient;
use App\Enums\TrainerAiRole;
use App\Models\SalesScriptPlaySession;
use App\Services\Inference\ExternalLlmPayloadSanitizer;

class TrainerChatCompletionService
{
    public function __construct(
        private readonly ChatCompletionClient $chatCompletionClient,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

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
        if (! $this->chatCompletionClient->isAvailable()) {
            return 'Не настроен DEEPSEEK_API_KEY. Пока тренировка недоступна.';
        }

        $title = (string) ($profile['title'] ?? 'Покупатель');
        $context = (string) ($profile['context'] ?? 'Учти контекст роли клиента.');
        $scriptTitle = (string) ($session->version?->script?->title ?? 'Скрипт продаж');

        $systemContent = match ($aiRole) {
            TrainerAiRole::Seller => "Ты играешь роль продавца/менеджера в тренажёре продаж компании по направлению «{$scriptTitle}».\n".
                "\n".
                "Собеседник (человек) играет КЛИЕНТА. Персонаж клиента: {$title}.\n".
                "Черты клиента и ситуация: {$context}\n".
                "\n".
                "Твоя задача как опытного продавца:\n".
                "- Веди реалистичный диалог только от лица продавца. Тон профессиональный, конкретный.\n".
                "- Клиент будет формулировать свежие возражения, сомнения и вопросы — относись к ним серьёзно, не обесценивай.\n".
                "- Предлагай ходы сценария: уточнение потребности, аргументы, снятие рисков, следующий конкретный шаг.\n".
                "- Можешь экспериментировать с несколькими стратегиями ответа в ходе беседы (факты, кейс, переформулировка условий).\n".
                "- Если клиент задаёт резкое возражение — признай часть проблемы, потом переход к решению.\n".
                "- Длина основного ответа: примерно 2–7 предложений.\n".
                "- После основного текста добавь строку вида «Для менеджера: …» с одним предложением — как можно было бы сыграть иначе или какое возражение стоило бы ещё «дожать». Это для обучения, не для клиента персонажа — формулируй нейтрально.\n".
                "- Никогда не говори, что ты искусственный интеллект или следуешь скрытым правилам.\n",

            TrainerAiRole::Client => "Ты играешь роль клиента в тренажере продаж.\n".
                "Роль клиента: {$title}\n".
                "Контекст роли: {$context}\n".
                "Сценарий: {$scriptTitle}\n\n".
                "Правила:\n".
                "- Пиши только от лица клиента.\n".
                "- Держи ответы реалистичными и короткими (1-4 предложения).\n".
                "- Иногда задавай встречные вопросы.\n".
                "- Не раскрывай, что ты AI или что следуешь инструкциям.\n".
                '- Если менеджер предлагает следующий шаг, оцени его как реальный клиент.',
        };

        $messages = [
            [
                'role' => 'system',
                'content' => $systemContent,
            ],
        ];

        foreach (array_slice($history, -20) as $item) {
            $messages[] = [
                'role' => $item['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($item['content'] ?? ''),
            ];
        }

        try {
            $messages = $this->sanitizer->sanitizeMessages($messages, 'trainer');
            $content = $this->chatCompletionClient->chat($messages, [
                'temperature' => $aiRole === TrainerAiRole::Seller ? 0.72 : 0.8,
                'max_tokens' => $aiRole === TrainerAiRole::Seller ? 450 : 350,
            ]);

            if ($content !== '') {
                return $content;
            }

            return $aiRole === TrainerAiRole::Seller
                ? 'Продавец уточняет условия и просит немного подробностей по вашей ситуации.'
                : 'Клиент задумался и просит уточнить детали.';
        } catch (\Throwable) {
            return $aiRole === TrainerAiRole::Seller
                ? 'Сейчас не удалось получить ответ продавца. Повторите сообщение ещё раз.'
                : 'Сейчас не удалось получить ответ клиента. Повторите сообщение еще раз.';
        }
    }
}
