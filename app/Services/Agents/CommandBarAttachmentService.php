<?php

namespace App\Services\Agents;

use App\Models\User;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Support\DocumentUploadLimits;
use App\Support\RoleAccess;
use Illuminate\Http\UploadedFile;

final class CommandBarAttachmentService
{
    public function __construct(
        private readonly DocumentTextExtractor $textExtractor,
        private readonly ContractorPrintFormChangeRequestService $printFormChanges,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null
     * }
     */
    public function process(array $files): array
    {
        $maxFiles = max(1, (int) config('ai.command_bar.max_attachment_files', 3));
        $maxChars = max(500, (int) config('ai.command_bar.max_attachment_chars', 12000));
        $items = [];
        $allWarnings = [];

        foreach (array_slice($files, 0, $maxFiles) as $file) {
            $extracted = $this->textExtractor->extractFromUpload($file);
            $items[] = [
                'name' => $file->getClientOriginalName(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'method' => (string) ($extracted['method'] ?? 'none'),
                'warnings' => is_array($extracted['warnings'] ?? null) ? $extracted['warnings'] : [],
                'text' => trim((string) ($extracted['text'] ?? '')),
                'size_bytes' => (int) $file->getSize(),
            ];
            $allWarnings = array_merge($allWarnings, $items[array_key_last($items)]['warnings']);
        }

        $combined = trim(implode("\n\n---\n\n", array_filter(
            array_map(
                static fn (array $item): string => $item['text'] !== ''
                    ? "=== {$item['name']} ===\n{$item['text']}"
                    : '',
                $items,
            ),
        )));

        $truncated = false;
        if (mb_strlen($combined) > $maxChars) {
            $combined = mb_substr($combined, 0, $maxChars);
            $truncated = true;
            $allWarnings[] = 'Текст из вложений обрезан до '.$maxChars.' символов для ассистента.';
        }

        $hasAnyText = $combined !== '';
        $hardFailure = ! $hasAnyText;
        $failureMessage = null;

        if ($hardFailure) {
            $unsupported = array_filter($items, static fn (array $item): bool => ($item['method'] ?? '') === 'unsupported');
            if ($unsupported !== []) {
                $failureMessage = 'Формат файла не поддерживается для автоматического чтения. Загрузите PDF или DOCX, либо вставьте текст в сообщение.';
            } else {
                $failureMessage = 'Не удалось извлечь текст из приложенных файлов. Проверьте, что документ не пуст и не состоит только из скана без распознавания.';
            }
        }

        return [
            'items' => $items,
            'combined_text' => $combined,
            'truncated' => $truncated,
            'hard_failure' => $hardFailure,
            'failure_message' => $failureMessage,
            'warnings' => array_values(array_unique($allWarnings)),
        ];
    }

    /**
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     * @param  array{slug?: string, display_name?: string}|null  $persona
     * @return array{
     *     intent: string,
     *     intent_label: string,
     *     allowed: bool,
     *     blockers: list<array{code: string, kind: string, message: string}>,
     *     suggested_tools: list<string>,
     *     prompt_supplement: string
     * }
     */
    public function assess(User $user, string $message, array $batch, ?array $persona = null): array
    {
        $intent = $this->detectIntent($message, $batch, $persona);
        $blockers = [];
        $suggestedTools = [];

        if ($intent === 'order_intake') {
            $suggestedTools = ['create_order_intake_draft_from_text', 'remember_order_intake_phrase'];
            if (! (bool) config('ai.order_intake.enabled', true)) {
                $blockers[] = [
                    'code' => 'order_intake_disabled',
                    'kind' => 'capability',
                    'message' => 'Распознавание заявок из текста отключено в настройках системы.',
                ];
            } elseif (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
                $blockers[] = [
                    'code' => 'visibility_orders',
                    'kind' => 'access',
                    'message' => 'Область «Заказы» недоступна вашей роли — создать черновик заявки нельзя.',
                ];
            } elseif ($batch['combined_text'] === '' && trim($message) === '') {
                $blockers[] = [
                    'code' => 'empty_instruction',
                    'kind' => 'user_intent',
                    'message' => 'Укажите в сообщении, что нужно сделать с файлом (например: «создай заявку на перевозку»).',
                ];
            }
        } elseif ($intent === 'basic_terms') {
            $suggestedTools = ['get_print_form_basic_terms', 'get_print_form_templates_insights', 'upsert_print_form_basic_terms', 'submit_contractor_print_form_change'];
            $canDirect = $this->printFormChanges->canDirectManagePrintForm($user);

            if (! $canDirect) {
                $blockers[] = [
                    'code' => 'visibility_print_forms',
                    'kind' => 'access',
                    'message' => 'Базовые условия и шаблоны печати недоступны вашей роли.',
                ];
            } elseif ($batch['combined_text'] === '') {
                $blockers[] = [
                    'code' => 'empty_extraction',
                    'kind' => 'extraction',
                    'message' => 'Не удалось прочитать текст условий из файла.',
                ];
            }
        } elseif ($intent === 'unknown') {
            if (trim($message) === '' && $batch['combined_text'] === '') {
                $blockers[] = [
                    'code' => 'intent_unclear',
                    'kind' => 'user_intent',
                    'message' => 'Напишите, что нужно сделать с файлом (заявка, базовые условия, справка и т.д.).',
                ];
            }
        }

        $allowed = $blockers === [] || $this->onlySoftBlockers($blockers);

        return [
            'intent' => $intent,
            'intent_label' => $this->intentLabel($intent),
            'allowed' => $allowed,
            'blockers' => $blockers,
            'suggested_tools' => $suggestedTools,
            'prompt_supplement' => $this->buildPromptSupplement($batch, $intent, $blockers, $suggestedTools),
        ];
    }

    /**
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     * @param  array{
     *     intent: string,
     *     intent_label: string,
     *     allowed: bool,
     *     blockers: list<array{code: string, kind: string, message: string}>,
     *     suggested_tools: list<string>,
     *     prompt_supplement: string
     * }  $assessment
     */
    public function buildAugmentedUserMessage(string $message, array $batch, array $assessment): string
    {
        $lines = [];
        $fileNames = array_map(static fn (array $item): string => $item['name'], $batch['items']);
        $lines[] = '[Вложения: '.implode(', ', $fileNames).']';

        $userPart = trim($message);
        if ($userPart !== '') {
            $lines[] = 'Запрос пользователя: '.$userPart;
        }

        if ($batch['combined_text'] !== '') {
            $lines[] = "Извлечённый текст документов:\n".$batch['combined_text'];
        }

        if (($batch['warnings'] ?? []) !== []) {
            $lines[] = 'Предупреждения при чтении: '.implode(' ', $batch['warnings']);
        }

        $lines[] = 'Предполагаемое намерение: '.$assessment['intent_label'].'.';
        if ($assessment['suggested_tools'] !== []) {
            $lines[] = 'Релевантные инструменты: '.implode(', ', $assessment['suggested_tools']).'.';
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     */
    public function analyticsPrompt(string $message, array $batch): string
    {
        $names = implode(', ', array_map(static fn (array $item): string => $item['name'], $batch['items']));
        $prefix = '[attachments: '.$names.']';
        $text = trim($message);

        return $text !== '' ? $prefix.' '.$text : $prefix;
    }

    /**
     * @param  array{
     *     intent: string,
     *     intent_label: string,
     *     allowed: bool,
     *     blockers: list<array{code: string, kind: string, message: string}>,
     *     suggested_tools: list<string>,
     *     prompt_supplement: string
     * }  $assessment
     * @param  list<string>  $toolsUsed
     * @return array{code: string, kind: string, reason: string, intent: string}|null
     */
    public function detectCapabilityGap(string $reply, array $assessment, array $toolsUsed): ?array
    {
        $hardBlockers = array_filter(
            $assessment['blockers'],
            static fn (array $blocker): bool => in_array($blocker['kind'], ['access', 'extraction', 'capability'], true),
        );

        if ($hardBlockers !== []) {
            $first = $hardBlockers[array_key_first($hardBlockers)];

            return [
                'code' => $first['code'],
                'kind' => $first['kind'],
                'reason' => $first['message'],
                'intent' => $assessment['intent'],
            ];
        }

        $lower = mb_strtolower(trim($reply));
        $refused = $this->replyLooksLikeRefusal($lower);

        if (! $refused) {
            if ($assessment['intent'] === 'order_intake' && ! in_array('create_order_intake_draft_from_text', $toolsUsed, true)) {
                return null;
            }

            if ($assessment['intent'] === 'basic_terms' && $toolsUsed === []) {
                return null;
            }

            return null;
        }

        if ($assessment['intent'] === 'unknown') {
            return [
                'code' => 'intent_unclear',
                'kind' => 'user_intent',
                'reason' => 'Пользователь не уточнил задачу по файлу или запрос вне возможностей ассистента.',
                'intent' => $assessment['intent'],
            ];
        }

        $expectedTools = $assessment['suggested_tools'];
        $usedRelevant = array_intersect($expectedTools, $toolsUsed) !== [];

        if ($usedRelevant) {
            return null;
        }

        $accessBlocker = null;
        foreach ($assessment['blockers'] as $blocker) {
            if ($blocker['kind'] === 'access') {
                $accessBlocker = $blocker;
                break;
            }
        }

        if ($accessBlocker !== null) {
            return [
                'code' => $accessBlocker['code'],
                'kind' => 'access',
                'reason' => $accessBlocker['message'],
                'intent' => $assessment['intent'],
            ];
        }

        return [
            'code' => 'assistant_refused_without_tool',
            'kind' => 'capability',
            'reason' => 'Ассистент отказал без вызова инструмента для распознанного намерения «'.$assessment['intent_label'].'».',
            'intent' => $assessment['intent'],
        ];
    }

    /**
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     * @param  array{
     *     intent: string,
     *     intent_label: string,
     *     allowed: bool,
     *     blockers: list<array{code: string, kind: string, message: string}>,
     *     suggested_tools: list<string>,
     *     prompt_supplement: string
     * }  $assessment
     * @return array<string, mixed>
     */
    public function metadataForTurn(array $batch, array $assessment): array
    {
        $gap = null;
        if ($batch['hard_failure']) {
            $gap = [
                'code' => str_contains(implode(' ', $batch['warnings'] ?? []), 'не поддерживается')
                    ? 'unsupported_format'
                    : 'empty_extraction',
                'kind' => 'extraction',
                'reason' => (string) ($batch['failure_message'] ?? 'Не удалось прочитать вложение.'),
                'intent' => $assessment['intent'],
            ];
        }

        return [
            'file_count' => count($batch['items']),
            'file_names' => array_map(static fn (array $item): string => $item['name'], $batch['items']),
            'combined_text_chars' => mb_strlen($batch['combined_text']),
            'truncated' => $batch['truncated'],
            'intent' => $assessment['intent'],
            'intent_label' => $assessment['intent_label'],
            'blockers' => $assessment['blockers'],
            'gap' => $gap,
        ];
    }

    public static function maxUploadBytes(): int
    {
        return (int) DocumentUploadLimits::forSharedInertia()['absolute_max_bytes'];
    }

    /**
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     * @param  array{slug?: string}|null  $persona
     */
    private function detectIntent(string $message, array $batch, ?array $persona): string
    {
        $personaSlug = is_array($persona) ? (string) ($persona['slug'] ?? '') : '';
        if ($personaSlug === 'yurik') {
            return 'basic_terms';
        }

        $haystack = mb_strtolower(trim($message.' '.$batch['combined_text']));

        if ($this->matchesAny($haystack, [
            'базов', 'услови', 'cp_', 'dp_', 'шаблон', 'договор-заяв', 'печатн', 'docx шаблон',
            'норм заявк', 'норм', 'юрик', 'сторон', 'заказчик', 'перевозчик', 'по аналогии',
        ])) {
            return 'basic_terms';
        }

        if ($this->matchesAny($haystack, [
            'заявк', 'перевоз', 'заказ', 'маршрут', 'груз', 'рейс', 'intake', 'погруз', 'выгруз',
            'ставк', 'тариф', 'фура', 'тонн',
        ])) {
            return 'order_intake';
        }

        return 'unknown';
    }

    /**
     * @param  list<array{code: string, kind: string, message: string}>  $blockers
     */
    private function onlySoftBlockers(array $blockers): bool
    {
        foreach ($blockers as $blocker) {
            if ($blocker['kind'] !== 'user_intent') {
                return false;
            }
        }

        return $blockers !== [];
    }

    /**
     * @param  list<array{code: string, kind: string, message: string}>  $blockers
     * @param  list<string>  $suggestedTools
     * @param  array{
     *     items: list<array{name: string, extension: string, method: string, warnings: list<string>, text: string, size_bytes: int}>,
     *     combined_text: string,
     *     truncated: bool,
     *     hard_failure: bool,
     *     failure_message: string|null,
     *     warnings?: list<string>
     * }  $batch
     */
    private function buildPromptSupplement(array $batch, string $intent, array $blockers, array $suggestedTools): string
    {
        $lines = [
            '[Режим вложений] Пользователь приложил файл(ы). Текст извлечён автоматически — используй его как источник, не выдумывай содержимое.',
        ];

        if ($intent === 'order_intake') {
            $lines[] = 'Если данных достаточно — create_order_intake_draft_from_text с instruction = запрос пользователя + извлечённый текст. Если не хватает своя компания / оплата / маршрут — задай 1–2 уточняющих вопроса.';
        } elseif ($intent === 'basic_terms') {
            $lines[] = 'get_print_form_basic_terms — прочитать пункты (party carrier/customer). Для «по аналогии» — прочитай источник, сохрани upsert_print_form_basic_terms для другой party. Точка с пробелом в начале пункта — часть текста items.';
        } else {
            $lines[] = 'Если намерение неясно — спроси, что сделать с файлом. Не выполняй опасные изменения без явного запроса.';
        }

        if ($blockers !== []) {
            $lines[] = 'Ограничения до вызова tools:';
            foreach ($blockers as $blocker) {
                $lines[] = '- '.$blocker['message'];
            }
            $lines[] = 'Если действие недоступно — ответь честно («пока не могу этого делать» / «вам это недоступно») и объясни причину простыми словами.';
        }

        if ($suggestedTools !== []) {
            $lines[] = 'Релевантные tools: '.implode(', ', $suggestedTools).'.';
        }

        if ($batch['truncated']) {
            $lines[] = 'Текст файла обрезан по лимиту — предупреди пользователя, если данных может не хватать.';
        }

        return implode("\n", $lines);
    }

    private function intentLabel(string $intent): string
    {
        return match ($intent) {
            'order_intake' => 'заявка на заказ',
            'basic_terms' => 'базовые условия / печатные формы',
            default => 'не определено',
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function replyLooksLikeRefusal(string $lowerReply): bool
    {
        foreach ([
            'пока не могу',
            'не могу этого делать',
            'вам это недоступно',
            'вам недоступ',
            'нет прав',
            'нет доступа',
            'недоступно для ваш',
            'не поддерживается',
            'не могу обработать',
            'не могу прочитать',
        ] as $phrase) {
            if (str_contains($lowerReply, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
