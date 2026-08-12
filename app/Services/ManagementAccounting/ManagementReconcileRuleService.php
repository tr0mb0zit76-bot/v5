<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\ManagementReconcileRule;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Support\PaymentMatchToken;
use Illuminate\Support\Facades\Schema;

class ManagementReconcileRuleService
{
    /** @var list<string> */
    private const STOPWORDS = [
        'оплата', 'платеж', 'платёж', 'перевод', 'списание', 'поступление', 'зачисление',
        'банк', 'банка', 'тинькофф',
        'ндс', 'налог', 'договор', 'счет', 'счёт', 'карта', 'руб', 'рублей', 'rur', 'rub',
        'от', 'по', 'за', 'из', 'для', 'без', 'или', 'и', 'на', 'в', 'с', 'со', 'к', 'ко',
        'ооо', 'ао', 'пао', 'зао', 'ип', 'г', 'ул', 'д', 'кв', 'рф',
    ];

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: ?int,
     *     suggested_user_id: ?int,
     *     rule_id: int
     * }
     */
    public function matchDescription(string $description, string $direction): ?array
    {
        if (! Schema::hasTable('management_reconcile_rules')) {
            return null;
        }

        $normalized = mb_strtolower(trim($description));

        $rules = ManagementReconcileRule::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('times_applied')
            ->get();

        foreach ($rules as $rule) {
            $keyword = mb_strtolower(trim((string) $rule->keyword));
            if ($keyword === '' || ! str_contains($normalized, $keyword)) {
                continue;
            }

            if ($rule->direction !== null && $rule->direction !== '' && $rule->direction !== $direction) {
                continue;
            }

            $suggestion = $this->suggestionFromRule($rule);

            return $suggestion;
        }

        return null;
    }

    /**
     * @return array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: ?int,
     *     suggested_user_id: ?int,
     *     rule_id: int
     * }
     */
    private function suggestionFromRule(ManagementReconcileRule $rule): array
    {
        $orderId = null;
        $scheduleId = $rule->payment_schedule_id;

        if ($rule->order_number !== null && $rule->order_number !== '') {
            $order = Order::query()->where('order_number', $rule->order_number)->first();
            $orderId = $order?->id;
        }

        if ($scheduleId === null && $orderId !== null) {
            $scheduleId = PaymentSchedule::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('id');
        }

        return [
            'match_type' => (string) $rule->allocation_type,
            'match_confidence' => 95,
            'match_notes' => 'Правило разнесения #'.$rule->id.($rule->notes ? ': '.$rule->notes : ''),
            'suggested_order_id' => $orderId,
            'suggested_payment_schedule_id' => $scheduleId !== null ? (int) $scheduleId : null,
            'suggested_category_id' => $rule->category_id,
            'suggested_user_id' => $rule->user_id,
            'rule_id' => $rule->id,
        ];
    }

    /**
     * @param  array{
     *     keyword: string,
     *     direction?: ?string,
     *     allocation_type: string,
     *     category_id?: ?int,
     *     user_id?: ?int,
     *     order_number?: ?string,
     *     payment_schedule_id?: ?int,
     *     notes?: ?string,
     *     priority?: ?int
     * }  $payload
     */
    public function remember(User $user, array $payload): ManagementReconcileRule
    {
        $keyword = mb_strtolower(trim($payload['keyword']));
        $direction = $payload['direction'] ?? null;
        $allocationType = (string) $payload['allocation_type'];

        $existing = ManagementReconcileRule::query()
            ->where('keyword', $keyword)
            ->where('allocation_type', $allocationType)
            ->when(
                $direction === null || $direction === '',
                fn ($q) => $q->where(fn ($inner) => $inner->whereNull('direction')->orWhere('direction', '')),
                fn ($q) => $q->where('direction', $direction),
            )
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'category_id' => $payload['category_id'] ?? $existing->category_id,
                'user_id' => $payload['user_id'] ?? $existing->user_id,
                'order_number' => array_key_exists('order_number', $payload)
                    ? $payload['order_number']
                    : $existing->order_number,
                'payment_schedule_id' => array_key_exists('payment_schedule_id', $payload)
                    ? $payload['payment_schedule_id']
                    : $existing->payment_schedule_id,
                'notes' => $payload['notes'] ?? $existing->notes,
                'priority' => (int) ($payload['priority'] ?? max(100, (int) $existing->priority)),
                'times_applied' => ((int) $existing->times_applied) + 1,
                'is_active' => true,
            ]);
            $existing->save();

            return $existing->fresh();
        }

        return ManagementReconcileRule::query()->create([
            'created_by' => $user->id,
            'keyword' => $keyword,
            'direction' => $direction,
            'allocation_type' => $allocationType,
            'category_id' => $payload['category_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'payment_schedule_id' => $payload['payment_schedule_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'priority' => (int) ($payload['priority'] ?? 100),
            'times_applied' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Сохранить правило по фактическому разнесению строки (обучение на исправлениях).
     */
    public function rememberFromAllocatedLine(
        User $user,
        ManagementStatementLine $line,
        string $keyword,
        ?string $notes = null,
    ): ManagementReconcileRule {
        $allocationType = match ((string) $line->match_type) {
            'operational', 'operational_split' => 'operational',
            'payroll' => 'payroll',
            default => 'category',
        };

        $orderNumber = null;
        if ($line->allocation_order_id !== null) {
            $orderNumber = Order::query()->whereKey($line->allocation_order_id)->value('order_number');
        }

        // Операционка: не цепляем конкретную строку графика — она погасится.
        // order_number только если он уже есть в назначении (тот же заказ ещё раз).
        $scheduleId = null;
        $persistOrderNumber = null;
        if ($allocationType === 'operational') {
            $description = (string) $line->description;
            if (is_string($orderNumber) && $orderNumber !== '' && str_contains(mb_strtolower($description), mb_strtolower($orderNumber))) {
                $persistOrderNumber = $orderNumber;
            }
        } else {
            $persistOrderNumber = is_string($orderNumber) ? $orderNumber : null;
            $scheduleId = $line->allocation_payment_schedule_id;
        }

        return $this->remember($user, [
            'keyword' => $keyword,
            'direction' => $line->direction,
            'allocation_type' => $allocationType,
            'category_id' => $line->allocation_category_id,
            'user_id' => $line->allocation_user_id,
            'order_number' => $persistOrderNumber,
            'payment_schedule_id' => $scheduleId,
            'notes' => $notes ?? 'Автообучение с разнесения #'.$line->id,
            'priority' => 120,
        ]);
    }

    /**
     * Автообучение после ручного разноса: явный keyword или извлечение из назначения.
     */
    public function learnFromManualAllocation(
        User $user,
        ManagementStatementLine $line,
        ?string $explicitKeyword = null,
        ?string $notes = null,
    ): ?ManagementReconcileRule {
        if (! Schema::hasTable('management_reconcile_rules')) {
            return null;
        }

        $description = (string) $line->description;
        if (PaymentMatchToken::containsToken($description)) {
            return null;
        }

        $keyword = trim((string) ($explicitKeyword ?? ''));
        if ($keyword === '') {
            $keyword = (string) ($this->extractAutoKeyword($description) ?? '');
        }

        if ($keyword === '') {
            return null;
        }

        return $this->rememberFromAllocatedLine($user, $line, $keyword, $notes);
    }

    /**
     * Извлечь устойчивый фрагмент назначения для правила.
     */
    public function extractAutoKeyword(string $description): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
        if ($text === '') {
            return null;
        }

        if (PaymentMatchToken::containsToken($text)) {
            return null;
        }

        if (preg_match('/\b(\d{10}|\d{12})\b/u', $text, $innMatch) === 1) {
            return $innMatch[1];
        }

        if (preg_match('/(?:ооо|ао|пао|зао|ип)\s+[«"«]?([\p{L}\d][\p{L}\d\-\.\s]{2,40})[»"»]?/ui', $text, $orgMatch) === 1) {
            $org = trim(preg_replace('/\s+/u', ' ', $orgMatch[0]) ?? '');
            $org = mb_strtolower($org);
            if (mb_strlen($org) >= 5) {
                return mb_substr($org, 0, 80);
            }
        }

        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/\b\d+[.,]\d+\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d{2}[.\-\/]\d{2}[.\-\/]\d{2,4}\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d{5,}\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $normalized) ?? $normalized;
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $meaningful = [];
        foreach ($tokens as $token) {
            $token = trim($token, '-');
            if ($token === '' || mb_strlen($token) < 3) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            if (preg_match('/^\d+$/u', $token) === 1) {
                continue;
            }
            $meaningful[] = $token;
        }

        if ($meaningful === []) {
            return null;
        }

        // 2–3 значимых слова подряд — устойчивее, чем одно короткое.
        if (count($meaningful) >= 2) {
            $phrase = implode(' ', array_slice($meaningful, 0, 3));
            if (mb_strlen($phrase) >= 5) {
                return mb_substr($phrase, 0, 80);
            }
        }

        $best = $meaningful[0];
        foreach ($meaningful as $token) {
            if (mb_strlen($token) > mb_strlen($best)) {
                $best = $token;
            }
        }

        if (mb_strlen($best) < 4) {
            return null;
        }

        return mb_substr($best, 0, 80);
    }
}
