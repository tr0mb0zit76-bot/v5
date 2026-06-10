<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingMatchingService
{
    public function __construct(
        private readonly ManagementReconcileRuleService $reconcileRules,
    ) {}

    /**
     * @return array{
     *     match_type: ?string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: ?int,
     *     suggested_user_id: ?int
     * }
     */
    public function suggestForLine(ManagementStatementLine $line): array
    {
        $description = mb_strtolower($line->description);
        $direction = $line->direction;
        $amount = (float) $line->amount;
        $operationDate = $line->operation_date?->toDateString();

        $ruleMatch = $this->reconcileRules->matchDescription($description, $direction);
        if ($ruleMatch !== null) {
            unset($ruleMatch['rule_id']);

            return $ruleMatch;
        }

        $operational = $this->matchOperational($description, $direction, $amount, $operationDate);
        if ($operational !== null) {
            return $operational;
        }

        $payroll = $this->matchPayroll($description, $direction, $amount);
        if ($payroll !== null) {
            return $payroll;
        }

        $category = $this->matchCategoryByKeywords($description, $direction);
        if ($category !== null) {
            return $category;
        }

        return [
            'match_type' => null,
            'match_confidence' => 0,
            'match_notes' => null,
            'suggested_order_id' => null,
            'suggested_payment_schedule_id' => null,
            'suggested_category_id' => $this->defaultCategoryId('unclassified'),
            'suggested_user_id' => null,
        ];
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: int,
     *     suggested_payment_schedule_id: int,
     *     suggested_category_id: int,
     *     suggested_user_id: null
     * }
     */
    private function matchOperational(string $description, string $direction, float $amount, ?string $operationDate): ?array
    {
        $orderNumber = $this->extractOrderNumber($description);
        if ($orderNumber === null) {
            return null;
        }

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->first();

        if ($order === null) {
            return [
                'match_type' => 'operational',
                'match_confidence' => 40,
                'match_notes' => 'Номер заявки найден, заказ не найден: '.$orderNumber,
                'suggested_order_id' => null,
                'suggested_payment_schedule_id' => null,
                'suggested_category_id' => $this->defaultCategoryId($direction === 'in' ? 'operational_customer_in' : 'operational_carrier_out'),
                'suggested_user_id' => null,
            ];
        }

        $party = $direction === 'in' ? 'customer' : 'carrier';
        $scheduleQuery = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', $party);

        if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            $scheduleQuery->where(function ($query) use ($amount): void {
                $query->where('remaining_amount', '>=', $amount - 0.01)
                    ->orWhere('amount', '>=', $amount - 0.01);
            });
        } else {
            $scheduleQuery->where('amount', '>=', $amount - 0.01);
        }

        if ($operationDate !== null) {
            $scheduleQuery->orderByRaw('ABS(DATEDIFF(COALESCE(planned_date, ?), ?))', [$operationDate, $operationDate]);
        }

        $schedule = $scheduleQuery->orderBy('id')->first();

        $confidence = $schedule !== null ? 90 : 60;
        $categoryCode = $direction === 'in' ? 'operational_customer_in' : 'operational_carrier_out';

        return [
            'match_type' => 'operational',
            'match_confidence' => $confidence,
            'match_notes' => $schedule === null ? 'Строка графика не найдена по сумме' : null,
            'suggested_order_id' => $order->id,
            'suggested_payment_schedule_id' => $schedule?->id,
            'suggested_category_id' => $this->defaultCategoryId($categoryCode),
            'suggested_user_id' => null,
        ];
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: null,
     *     suggested_payment_schedule_id: null,
     *     suggested_category_id: int,
     *     suggested_user_id: int
     * }
     */
    private function matchPayroll(string $description, string $direction, float $amount): ?array
    {
        if ($direction !== 'out' || $amount <= 0) {
            return null;
        }

        $user = $this->matchUserByDescription($description);
        if ($user === null) {
            return null;
        }

        return [
            'match_type' => 'payroll',
            'match_confidence' => 75,
            'match_notes' => 'Совпадение по ФИО сотрудника',
            'suggested_order_id' => null,
            'suggested_payment_schedule_id' => null,
            'suggested_category_id' => $this->defaultCategoryId('payroll_paid_sales'),
            'suggested_user_id' => $user->id,
        ];
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: null,
     *     suggested_payment_schedule_id: null,
     *     suggested_category_id: int,
     *     suggested_user_id: null
     * }
     */
    private function matchCategoryByKeywords(string $description, string $direction): ?array
    {
        $bankFeeKeywords = ['комисс', 'сбор', 'обслуживан'];
        foreach ($bankFeeKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return [
                    'match_type' => 'category',
                    'match_confidence' => 80,
                    'match_notes' => 'Ключевое слово: '.$keyword,
                    'suggested_order_id' => null,
                    'suggested_payment_schedule_id' => null,
                    'suggested_category_id' => $this->defaultCategoryId('bank_fees'),
                    'suggested_user_id' => null,
                ];
            }
        }

        $serviceKeywords = ['ати', 'лиценз', 'подписк', 'сервис'];
        foreach ($serviceKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return [
                    'match_type' => 'category',
                    'match_confidence' => 70,
                    'match_notes' => 'Ключевое слово: '.$keyword,
                    'suggested_order_id' => null,
                    'suggested_payment_schedule_id' => null,
                    'suggested_category_id' => $this->defaultCategoryId('services_other'),
                    'suggested_user_id' => null,
                ];
            }
        }

        $cashCode = $direction === 'in' ? 'cash_other_in' : 'cash_other_out';

        return [
            'match_type' => 'category',
            'match_confidence' => 20,
            'match_notes' => 'Эвристика по направлению',
            'suggested_order_id' => null,
            'suggested_payment_schedule_id' => null,
            'suggested_category_id' => $this->defaultCategoryId($cashCode),
            'suggested_user_id' => null,
        ];
    }

    private function extractOrderNumber(string $description): ?string
    {
        if (preg_match('/АС[\s\-]*(\d{2})(\d{2})[\s\-]*(\d{4})/ui', $description, $matches) === 1) {
            return sprintf('АС-%s%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/AC[\s\-]*(\d{2})(\d{2})[\s\-]*(\d{4})/i', $description, $matches) === 1) {
            return sprintf('АС-%s%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        return null;
    }

    private function matchUserByDescription(string $description): ?User
    {
        $users = User::query()
            ->where('is_active', true)
            ->get(['id', 'name']);

        foreach ($users as $user) {
            $name = mb_strtolower(trim((string) $user->name));
            if ($name === '') {
                continue;
            }

            if (str_contains($description, $name)) {
                return $user;
            }

            $parts = preg_split('/\s+/u', $name) ?: [];
            if (count($parts) >= 2) {
                $surname = $parts[0];
                if (mb_strlen($surname) >= 3 && str_contains($description, $surname)) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function defaultCategoryId(string $code): ?int
    {
        return ManagementExpenseCategory::query()
            ->where('code', $code)
            ->value('id');
    }
}
