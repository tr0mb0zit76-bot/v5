<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementReconcileRule;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ManagementReconcileRuleService
{
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
            $keyword = mb_strtolower(trim($rule->keyword));
            if ($keyword === '' || ! str_contains($normalized, $keyword)) {
                continue;
            }

            if ($rule->direction !== null && $rule->direction !== '' && $rule->direction !== $direction) {
                continue;
            }

            return $this->suggestionFromRule($rule);
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
        return ManagementReconcileRule::query()->create([
            'created_by' => $user->id,
            'keyword' => mb_strtolower(trim($payload['keyword'])),
            'direction' => $payload['direction'] ?? null,
            'allocation_type' => $payload['allocation_type'],
            'category_id' => $payload['category_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'payment_schedule_id' => $payload['payment_schedule_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'priority' => (int) ($payload['priority'] ?? 100),
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
            'operational' => 'operational',
            'payroll' => 'payroll',
            default => 'category',
        };

        $orderNumber = null;
        if ($line->allocation_order_id !== null) {
            $orderNumber = Order::query()->whereKey($line->allocation_order_id)->value('order_number');
        }

        return $this->remember($user, [
            'keyword' => $keyword,
            'direction' => $line->direction,
            'allocation_type' => $allocationType,
            'category_id' => $line->allocation_category_id,
            'user_id' => $line->allocation_user_id,
            'order_number' => is_string($orderNumber) ? $orderNumber : null,
            'payment_schedule_id' => $line->allocation_payment_schedule_id,
            'notes' => $notes,
            'priority' => 120,
        ]);
    }
}
