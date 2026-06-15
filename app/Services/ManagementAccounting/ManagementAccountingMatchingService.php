<?php

namespace App\Services\ManagementAccounting;

use App\Models\Contractor;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Support\ManagementCostCategoryCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingMatchingService
{
    public function __construct(
        private readonly ManagementReconcileRuleService $reconcileRules,
        private readonly ManagementOperationalCostCategoryResolver $costCategoryResolver,
    ) {}

    /**
     * @return array{
     *     match_type: ?string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: ?int,
     *     suggested_user_id: ?int,
     *     suggested_candidates: list<array{
     *         payment_schedule_id: int,
     *         order_id: int,
     *         order_number: ?string,
     *         planned_date: ?string,
     *         amount: float,
     *         contractor_label: string,
     *         party: ?string
     *     }>
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
            $ruleMatch['suggested_candidates'] = [];

            return $ruleMatch;
        }

        $operationalByOrder = $this->matchOperationalByOrderNumber($description, $direction, $amount, $operationDate);
        if ($operationalByOrder !== null) {
            return $operationalByOrder;
        }

        $operationalByInvoice = $this->matchOperationalByInvoiceNumber($description, $direction, $amount, $operationDate);
        if ($operationalByInvoice !== null) {
            return $operationalByInvoice;
        }

        $operationalByContractor = $this->matchOperationalByContractorAndAmount($description, $direction, $amount, $operationDate);
        if ($operationalByContractor !== null) {
            return $operationalByContractor;
        }

        $payroll = $this->matchPayroll($description, $direction, $amount);
        if ($payroll !== null) {
            return $payroll;
        }

        $category = $this->matchCategoryByKeywords($description, $direction);
        if ($category !== null) {
            return $category;
        }

        if ($amount > 0) {
            $manualCandidates = $this->operationalCandidatesForLine($line);

            if ($manualCandidates !== []) {
                $singleCandidate = count($manualCandidates) === 1 ? $manualCandidates[0] : null;

                return [
                    'match_type' => 'operational',
                    'match_confidence' => $singleCandidate !== null ? 55 : 45,
                    'match_notes' => $singleCandidate !== null
                        ? 'Возможное совпадение — подтвердите или выберите другую строку'
                        : 'Автоматически не определено — выберите строку графика',
                    'suggested_order_id' => $singleCandidate['order_id'] ?? null,
                    'suggested_payment_schedule_id' => $singleCandidate['payment_schedule_id'] ?? null,
                    'suggested_category_id' => $direction === 'in'
                        ? $this->defaultCategoryId('operational_customer_in')
                        : $this->suggestedCarrierCategoryId(
                            isset($singleCandidate['order_id']) ? (int) $singleCandidate['order_id'] : null,
                            null,
                        ),
                    'suggested_user_id' => null,
                    'suggested_candidates' => $manualCandidates,
                ];
            }
        }

        return [
            'match_type' => null,
            'match_confidence' => 0,
            'match_notes' => null,
            'suggested_order_id' => null,
            'suggested_payment_schedule_id' => null,
            'suggested_category_id' => $this->defaultCategoryId('unclassified'),
            'suggested_user_id' => null,
            'suggested_candidates' => [],
        ];
    }

    /**
     * @return list<array{
     *     payment_schedule_id: int,
     *     order_id: int,
     *     order_number: ?string,
     *     planned_date: ?string,
     *     amount: float,
     *     contractor_label: string,
     *     party: ?string
     * }>
     */
    public function operationalCandidatesForLine(ManagementStatementLine $line): array
    {
        if ($line->status === 'allocated' || (float) $line->amount <= 0) {
            return [];
        }

        $description = mb_strtolower($line->description);
        $operationDate = $line->operation_date?->toDateString();

        $candidates = $this->operationalCandidatesByContractorAndAmount(
            $description,
            $line->direction,
            (float) $line->amount,
            $operationDate,
        )->merge(
            $this->operationalCandidatesByInvoiceNumber(
                $description,
                $line->direction,
                (float) $line->amount,
                $operationDate,
            ),
        )->unique(fn (array $candidate): int => (int) $candidate['schedule']->id)->values();

        if ($candidates->isEmpty() && $line->direction === 'out') {
            $candidates = $this->operationalCandidatesByAmountOnly(
                $description,
                (float) $line->amount,
                $operationDate,
            );
        }

        return $this->serializeOperationalCandidates($candidates);
    }

    /**
     * @return list<array{
     *     payment_schedule_id: int,
     *     order_id: int,
     *     order_number: ?string,
     *     planned_date: ?string,
     *     amount: float,
     *     contractor_label: string,
     *     party: ?string,
     *     match_reason: ?string
     * }>
     */
    public function searchOperationalCandidates(ManagementStatementLine $line, ?string $search = null): array
    {
        if ($line->status === 'allocated' || (float) $line->amount <= 0) {
            return [];
        }

        $description = mb_strtolower($line->description);
        $operationDate = $line->operation_date?->toDateString();
        $search = mb_strtolower(trim((string) $search));

        $candidates = $this->operationalCandidatesByContractorAndAmount(
            $description,
            $line->direction,
            (float) $line->amount,
            $operationDate,
        )->merge(
            $this->operationalCandidatesByInvoiceNumber(
                $description,
                $line->direction,
                (float) $line->amount,
                $operationDate,
            ),
        );

        if ($search !== '') {
            $candidates = $candidates->merge(
                $this->operationalCandidatesBySearchQuery(
                    $search,
                    $line->direction,
                    (float) $line->amount,
                    $operationDate,
                ),
            );
        } elseif ($line->direction === 'out') {
            $candidates = $candidates->merge(
                $this->operationalCandidatesByAmountOnly(
                    $description,
                    (float) $line->amount,
                    $operationDate,
                ),
            );
        }

        $candidates = $candidates
            ->unique(fn (array $candidate): int => (int) $candidate['schedule']->id)
            ->values();

        return $this->serializeOperationalCandidates($candidates);
    }

    public function extractSearchHintFromDescription(string $description): ?string
    {
        $description = trim($description);

        if ($description === '') {
            return null;
        }

        if (preg_match('/(?:тк|ооо|оао|зао|пао|ип|ао|чп)\s+[«"]?[a-zа-яё0-9\-]+(?:\s+[a-zа-яё0-9\-]+){0,3}/ui', $description, $matches) === 1) {
            return trim($matches[0]);
        }

        $normalized = mb_strtolower($description);
        $stopWords = ['оплата', 'платеж', 'перевод', 'перевозка', 'счет', 'счёт', 'номер', 'без', 'ндс', 'руб', 'рублей'];
        $parts = preg_split('/[\s,.;:()«»""\/\-]+/u', $normalized) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);

            if (mb_strlen($part) < 5 || in_array($part, $stopWords, true)) {
                continue;
            }

            if (preg_match('/^\d+$/', $part) === 1) {
                continue;
            }

            return $part;
        }

        return null;
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: int,
     *     suggested_user_id: null,
     *     suggested_candidates: list<array<string, mixed>>
     * }
     */
    private function matchOperationalByOrderNumber(string $description, string $direction, float $amount, ?string $operationDate): ?array
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
                'suggested_category_id' => $this->suggestedCarrierCategoryId(null, null),
                'suggested_user_id' => null,
                'suggested_candidates' => [],
            ];
        }

        $party = $direction === 'in' ? 'customer' : 'carrier';
        $schedule = $this->findScheduleForOrder($order, $party, $amount, $operationDate);
        $confidence = $schedule !== null ? 90 : 60;
        $scheduleContractor = $schedule !== null
            ? $this->resolveScheduleContractor($schedule, $direction)
            : null;
        $candidates = $schedule !== null
            ? $this->serializeOperationalCandidates(collect([
                [
                    'schedule' => $schedule,
                    'contractor_label' => $scheduleContractor !== null
                        ? $this->contractorDisplayLabel($scheduleContractor)
                        : '—',
                    'order_number' => $order->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                ],
            ]))
            : [];

        return [
            'match_type' => 'operational',
            'match_confidence' => $confidence,
            'match_notes' => $schedule === null ? 'Строка графика не найдена по сумме' : null,
            'suggested_order_id' => $order->id,
            'suggested_payment_schedule_id' => $schedule?->id,
            'suggested_category_id' => $this->suggestedCarrierCategoryId(
                (int) $order->id,
                $schedule?->counterparty_id !== null ? (int) $schedule->counterparty_id : null,
            ),
            'suggested_user_id' => null,
            'suggested_candidates' => $candidates,
        ];
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: int,
     *     suggested_user_id: null,
     *     suggested_candidates: list<array<string, mixed>>
     * }
     */
    private function matchOperationalByContractorAndAmount(string $description, string $direction, float $amount, ?string $operationDate): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        $candidates = $this->operationalCandidatesByContractorAndAmount($description, $direction, $amount, $operationDate);
        if ($candidates->isEmpty()) {
            return null;
        }

        $serializedCandidates = $this->serializeOperationalCandidates($candidates);

        if ($candidates->count() > 1) {
            $orderNumbers = $candidates
                ->pluck('order_number')
                ->filter()
                ->unique()
                ->take(5)
                ->implode(', ');

            return [
                'match_type' => 'operational',
                'match_confidence' => 68,
                'match_notes' => 'Несколько заявок ('.$orderNumbers.'): выберите строку графика',
                'suggested_order_id' => null,
                'suggested_payment_schedule_id' => null,
                'suggested_category_id' => $direction === 'in'
                    ? $this->defaultCategoryId('operational_customer_in')
                    : $this->suggestedCarrierCategoryId(null, null),
                'suggested_user_id' => null,
                'suggested_candidates' => $serializedCandidates,
            ];
        }

        /** @var array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int} $best */
        $best = $candidates->first();
        $schedule = $best['schedule'];

        return [
            'match_type' => 'operational',
            'match_confidence' => 82,
            'match_notes' => 'Контрагент и сумма: '.$best['contractor_label'],
            'suggested_order_id' => $schedule->order_id,
            'suggested_payment_schedule_id' => $schedule->id,
            'suggested_category_id' => $direction === 'in'
                ? $this->defaultCategoryId('operational_customer_in')
                : $this->suggestedCarrierCategoryId(
                    $schedule->order_id !== null ? (int) $schedule->order_id : null,
                    $schedule->counterparty_id !== null ? (int) $schedule->counterparty_id : null,
                ),
            'suggested_user_id' => null,
            'suggested_candidates' => $serializedCandidates,
        ];
    }

    /**
     * @param  Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int}>  $candidates
     * @return list<array{
     *     payment_schedule_id: int,
     *     order_id: int,
     *     order_number: ?string,
     *     planned_date: ?string,
     *     amount: float,
     *     contractor_label: string,
     *     party: ?string
     * }>
     */
    private function serializeOperationalCandidates(Collection $candidates): array
    {
        return $candidates
            ->map(function (array $candidate): array {
                $schedule = $candidate['schedule'];

                return [
                    'payment_schedule_id' => $schedule->id,
                    'order_id' => (int) $schedule->order_id,
                    'order_number' => $candidate['order_number'],
                    'planned_date' => $schedule->planned_date?->toDateString(),
                    'amount' => $this->effectiveScheduleAmount($schedule),
                    'contractor_label' => $candidate['contractor_label'],
                    'party' => $schedule->party,
                    'match_reason' => $candidate['match_reason'] ?? null,
                    'match_reason_label' => $this->matchReasonLabel($candidate['match_reason'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int}>
     */
    private function operationalCandidatesByContractorAndAmount(
        string $description,
        string $direction,
        float $amount,
        ?string $operationDate,
    ): Collection {
        $parties = $direction === 'in' ? ['customer'] : ['carrier', 'contractor'];

        return $this->openOperationalSchedulesQuery()
            ->with([
                'order:id,order_number,customer_id,carrier_id,performers',
                'order.client:id,name,full_name',
                'order.carrier:id,name,full_name',
                'counterparty:id,name,full_name',
            ])
            ->whereIn('party', $parties)
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentSchedule $schedule): bool => $this->amountMatchesSchedule($schedule, $amount))
            ->map(function (PaymentSchedule $schedule) use ($description, $direction, $operationDate, $amount): ?array {
                $contractors = $this->contractorsForSchedule($schedule, $direction);
                $matchedContractor = null;

                foreach ($contractors as $contractor) {
                    if ($this->contractorLabelInDescription($description, $contractor)) {
                        $matchedContractor = $contractor;

                        break;
                    }
                }

                if ($matchedContractor === null) {
                    return null;
                }

                return [
                    'schedule' => $schedule,
                    'contractor_label' => $this->contractorDisplayLabel($matchedContractor),
                    'order_number' => $schedule->order?->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                    'amount_distance' => abs($this->effectiveScheduleAmount($schedule) - $amount),
                    'match_reason' => 'contractor',
                ];
            })
            ->filter()
            ->sortBy(fn (array $candidate): array => [
                $candidate['amount_distance'] >= 0.01 ? 1 : 0,
                $candidate['date_distance'],
                $candidate['schedule']->id,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float, match_reason: string}>
     */
    private function operationalCandidatesByAmountOnly(
        string $description,
        float $amount,
        ?string $operationDate,
    ): Collection {
        return $this->openOperationalSchedulesQuery()
            ->with([
                'order:id,order_number,customer_id,carrier_id,performers',
                'order.client:id,name,full_name',
                'order.carrier:id,name,full_name',
                'counterparty:id,name,full_name',
            ])
            ->whereIn('party', ['carrier', 'contractor'])
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentSchedule $schedule): bool => $this->amountMatchesSchedule($schedule, $amount))
            ->map(function (PaymentSchedule $schedule) use ($description, $operationDate, $amount): array {
                $contractors = $this->contractorsForSchedule($schedule, 'out');
                $contractor = $contractors[0] ?? null;
                $label = $contractor !== null ? $this->contractorDisplayLabel($contractor) : '—';

                return [
                    'schedule' => $schedule,
                    'contractor_label' => $label,
                    'order_number' => $schedule->order?->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                    'amount_distance' => abs($this->effectiveScheduleAmount($schedule) - $amount),
                    'match_reason' => $contractor !== null && $this->contractorLabelInDescription($description, $contractor)
                        ? 'contractor'
                        : 'amount_only',
                ];
            })
            ->sortBy(fn (array $candidate): array => [
                $candidate['match_reason'] === 'amount_only' ? 1 : 0,
                $candidate['amount_distance'] >= 0.01 ? 1 : 0,
                $candidate['date_distance'],
                $candidate['schedule']->id,
            ])
            ->take(20)
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float, match_reason: string}>
     */
    private function operationalCandidatesBySearchQuery(
        string $search,
        string $direction,
        float $amount,
        ?string $operationDate,
    ): Collection {
        $parties = $direction === 'in' ? ['customer'] : ['carrier', 'contractor'];

        return $this->openOperationalSchedulesQuery()
            ->with([
                'order:id,order_number,customer_id,carrier_id,performers',
                'order.client:id,name,full_name',
                'order.carrier:id,name,full_name',
                'counterparty:id,name,full_name',
            ])
            ->whereIn('party', $parties)
            ->orderBy('id')
            ->get()
            ->filter(function (PaymentSchedule $schedule) use ($search, $direction, $amount): bool {
                if (! $this->amountMatchesSchedule($schedule, $amount)) {
                    return false;
                }

                foreach ($this->contractorsForSchedule($schedule, $direction) as $contractor) {
                    if ($this->contractorMatchesSearch($contractor, $search)) {
                        return true;
                    }
                }

                $orderNumber = mb_strtolower((string) ($schedule->order?->order_number ?? ''));

                return $orderNumber !== '' && str_contains($orderNumber, $search);
            })
            ->map(function (PaymentSchedule $schedule) use ($direction, $operationDate, $amount, $search): array {
                $matchedContractor = null;

                foreach ($this->contractorsForSchedule($schedule, $direction) as $contractor) {
                    if ($this->contractorMatchesSearch($contractor, $search)) {
                        $matchedContractor = $contractor;

                        break;
                    }
                }

                $contractor = $matchedContractor ?? $this->resolveScheduleContractor($schedule, $direction);

                return [
                    'schedule' => $schedule,
                    'contractor_label' => $contractor !== null
                        ? $this->contractorDisplayLabel($contractor)
                        : '—',
                    'order_number' => $schedule->order?->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                    'amount_distance' => abs($this->effectiveScheduleAmount($schedule) - $amount),
                    'match_reason' => 'search',
                ];
            })
            ->sortBy(fn (array $candidate): array => [
                $candidate['amount_distance'] >= 0.01 ? 1 : 0,
                $candidate['date_distance'],
                $candidate['schedule']->id,
            ])
            ->take(20)
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float}>
     */
    private function operationalCandidatesByInvoiceNumber(
        string $description,
        string $direction,
        float $amount,
        ?string $operationDate,
    ): Collection {
        if ($amount <= 0 || ! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return collect();
        }

        $parties = $direction === 'in' ? ['customer'] : ['carrier', 'contractor'];

        return $this->openOperationalSchedulesQuery()
            ->with([
                'order:id,order_number,customer_id,carrier_id,performers',
                'order.client:id,name,full_name',
                'order.carrier:id,name,full_name',
                'counterparty:id,name,full_name',
            ])
            ->whereIn('party', $parties)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentSchedule $schedule): bool => $this->amountMatchesSchedule($schedule, $amount))
            ->filter(fn (PaymentSchedule $schedule): bool => $this->invoiceNumberMatchesDescription($description, $schedule->invoice_number))
            ->map(function (PaymentSchedule $schedule) use ($direction, $operationDate, $amount): array {
                $contractor = $this->resolveScheduleContractor($schedule, $direction);

                return [
                    'schedule' => $schedule,
                    'contractor_label' => $contractor !== null
                        ? $this->contractorDisplayLabel($contractor)
                        : '—',
                    'order_number' => $schedule->order?->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                    'amount_distance' => abs($this->effectiveScheduleAmount($schedule) - $amount),
                    'match_reason' => 'invoice',
                ];
            })
            ->sortBy(fn (array $candidate): array => [
                $candidate['amount_distance'] >= 0.01 ? 1 : 0,
                $candidate['date_distance'],
                $candidate['schedule']->id,
            ])
            ->values();
    }

    /**
     * @return ?array{
     *     match_type: string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: ?int,
     *     suggested_payment_schedule_id: ?int,
     *     suggested_category_id: int,
     *     suggested_user_id: null,
     *     suggested_candidates: list<array<string, mixed>>
     * }
     */
    private function matchOperationalByInvoiceNumber(string $description, string $direction, float $amount, ?string $operationDate): ?array
    {
        if ($amount <= 0 || ! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return null;
        }

        $candidates = $this->operationalCandidatesByInvoiceNumber($description, $direction, $amount, $operationDate);
        if ($candidates->isEmpty()) {
            return null;
        }

        $serializedCandidates = $this->serializeOperationalCandidates($candidates);

        if ($candidates->count() > 1) {
            $orderNumbers = $candidates
                ->pluck('order_number')
                ->filter()
                ->unique()
                ->take(5)
                ->implode(', ');

            return [
                'match_type' => 'operational',
                'match_confidence' => 72,
                'match_notes' => 'Несколько строк по номеру счёта ('.$orderNumbers.'): выберите график',
                'suggested_order_id' => null,
                'suggested_payment_schedule_id' => null,
                'suggested_category_id' => $direction === 'in'
                    ? $this->defaultCategoryId('operational_customer_in')
                    : $this->suggestedCarrierCategoryId(null, null),
                'suggested_user_id' => null,
                'suggested_candidates' => $serializedCandidates,
            ];
        }

        /** @var array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float} $best */
        $best = $candidates->first();
        $schedule = $best['schedule'];
        $invoiceNumber = trim((string) $schedule->invoice_number);
        $notes = $invoiceNumber !== ''
            ? 'Счёт '.$invoiceNumber.($best['contractor_label'] !== '—' ? ': '.$best['contractor_label'] : '')
            : 'Совпадение по номеру счёта';

        return [
            'match_type' => 'operational',
            'match_confidence' => 88,
            'match_notes' => $notes,
            'suggested_order_id' => $schedule->order_id,
            'suggested_payment_schedule_id' => $schedule->id,
            'suggested_category_id' => $direction === 'in'
                ? $this->defaultCategoryId('operational_customer_in')
                : $this->suggestedCarrierCategoryId(
                    $schedule->order_id !== null ? (int) $schedule->order_id : null,
                    $schedule->counterparty_id !== null ? (int) $schedule->counterparty_id : null,
                ),
            'suggested_user_id' => null,
            'suggested_candidates' => $serializedCandidates,
        ];
    }

    private function openOperationalSchedulesQuery(): Builder
    {
        $query = PaymentSchedule::query()
            ->whereNotIn('status', ['paid', 'cancelled']);

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function (Builder $partialQuery): void {
                $partialQuery->whereNull('is_partial')
                    ->orWhere('is_partial', false);
            });
        }

        return $query;
    }

    private function findScheduleForOrder(Order $order, string $party, float $amount, ?string $operationDate): ?PaymentSchedule
    {
        $scheduleQuery = $this->openOperationalSchedulesQuery()
            ->where('order_id', $order->id)
            ->where('party', $party);

        if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            $scheduleQuery->where(function (Builder $query) use ($amount): void {
                $query->where('remaining_amount', '>=', $amount - 0.01)
                    ->orWhere('amount', '>=', $amount - 0.01);
            });
        } else {
            $scheduleQuery->where('amount', '>=', $amount - 0.01);
        }

        if ($operationDate !== null) {
            $scheduleQuery->orderByRaw('ABS(DATEDIFF(COALESCE(planned_date, ?), ?))', [$operationDate, $operationDate]);
        }

        return $scheduleQuery->orderBy('id')->first();
    }

    private function amountMatchesSchedule(PaymentSchedule $schedule, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $openAmount = $this->effectiveScheduleAmount($schedule);

        if (abs($openAmount - $amount) < 0.01) {
            return true;
        }

        return $amount < $openAmount;
    }

    private function effectiveScheduleAmount(PaymentSchedule $schedule): float
    {
        if (Schema::hasColumn('payment_schedules', 'remaining_amount') && $schedule->remaining_amount !== null) {
            return (float) $schedule->remaining_amount;
        }

        return (float) $schedule->amount;
    }

    private function resolveScheduleContractor(PaymentSchedule $schedule, string $direction): ?Contractor
    {
        if ($direction === 'in') {
            return $schedule->order?->client;
        }

        if ($schedule->counterparty !== null) {
            return $schedule->counterparty;
        }

        return $schedule->order?->carrier;
    }

    /**
     * @return list<Contractor>
     */
    private function contractorsForSchedule(PaymentSchedule $schedule, string $direction): array
    {
        $contractors = [];
        $seen = [];

        $append = function (?Contractor $contractor) use (&$contractors, &$seen): void {
            if ($contractor === null) {
                return;
            }

            $id = (int) $contractor->id;

            if (isset($seen[$id])) {
                return;
            }

            $seen[$id] = true;
            $contractors[] = $contractor;
        };

        if ($direction === 'in') {
            $append($schedule->order?->client);

            return $contractors;
        }

        $append($schedule->counterparty);
        $append($schedule->order?->carrier);

        $order = $schedule->order;

        if ($order !== null && Schema::hasColumn('orders', 'performers')) {
            foreach ($this->performerContractors($order) as $contractor) {
                $append($contractor);
            }
        }

        return $contractors;
    }

    /**
     * @return list<Contractor>
     */
    private function performerContractors(Order $order): array
    {
        $ids = [];
        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (! empty($performer['contractor_id'])) {
                $ids[] = (int) $performer['contractor_id'];
            }

            foreach ($performer['split_carriers'] ?? [] as $slot) {
                if (is_array($slot) && ! empty($slot['contractor_id'])) {
                    $ids[] = (int) $slot['contractor_id'];
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Contractor::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'full_name'])
            ->all();
    }

    private function contractorMatchesSearch(Contractor $contractor, string $search): bool
    {
        $search = mb_strtolower(trim($search));

        if ($search === '') {
            return false;
        }

        if ($this->contractorLabelInDescription($search, $contractor)) {
            return true;
        }

        foreach ($this->contractorSearchTokens($contractor) as $token) {
            if (mb_strlen($token) >= 4 && str_contains($search, $token)) {
                return true;
            }

            if (mb_strlen($token) >= 4 && str_contains($token, $search)) {
                return true;
            }
        }

        return false;
    }

    private function matchReasonLabel(?string $reason): ?string
    {
        return match ($reason) {
            'contractor' => 'контрагент и сумма',
            'invoice' => 'номер счёта',
            'amount_only' => 'только сумма',
            'search' => 'поиск по названию',
            default => null,
        };
    }

    private function contractorDisplayLabel(Contractor $contractor): string
    {
        $name = trim((string) $contractor->name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($contractor->full_name ?? ''));
    }

    private function contractorLabelInDescription(string $description, Contractor $contractor): bool
    {
        $labels = array_values(array_unique(array_filter([
            mb_strtolower(trim((string) $contractor->name)),
            mb_strtolower(trim((string) ($contractor->full_name ?? ''))),
        ])));

        foreach ($labels as $label) {
            if (mb_strlen($label) >= 5 && str_contains($description, $label)) {
                return true;
            }

            $distinctive = $this->stripLegalFormPrefix($label);
            if (mb_strlen($distinctive) >= 4 && str_contains($description, $distinctive)) {
                return true;
            }
        }

        foreach ($this->contractorSearchTokens($contractor) as $token) {
            if (str_contains($description, $token)) {
                return true;
            }

            if (mb_strlen($token) >= 4 && str_contains($description, 'тк '.$token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function contractorSearchTokens(Contractor $contractor): array
    {
        $tokens = [];

        foreach ([(string) $contractor->name, (string) ($contractor->full_name ?? '')] as $source) {
            $normalized = mb_strtolower(trim($source));
            if ($normalized === '') {
                continue;
            }

            $normalized = $this->stripLegalFormPrefix($normalized);
            $parts = preg_split('/[\s«»""\.\(\),]+/u', $normalized) ?: [];

            foreach ($parts as $part) {
                $part = trim($part);
                if (mb_strlen($part) >= 5) {
                    $tokens[] = $part;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    private function invoiceNumberMatchesDescription(string $description, ?string $invoiceNumber): bool
    {
        if ($invoiceNumber === null || trim($invoiceNumber) === '') {
            return false;
        }

        $desc = mb_strtolower($description);
        $invoice = mb_strtolower(trim($invoiceNumber));

        if (str_contains($desc, $invoice)) {
            return true;
        }

        $invoiceDigits = preg_replace('/\D+/', '', $invoice) ?? '';
        if ($invoiceDigits === '' || strlen($invoiceDigits) < 3) {
            return false;
        }

        $descDigits = preg_replace('/\D+/', '', $desc) ?? '';

        return str_contains($descDigits, $invoiceDigits);
    }

    private function stripLegalFormPrefix(string $label): string
    {
        $label = trim((string) preg_replace('/^(ооо|оао|зао|пао|ип|ао|чп)\s+/u', '', $label));

        return trim((string) preg_replace('/^тк\s+/u', '', $label));
    }

    private function plannedDateDistanceDays(PaymentSchedule $schedule, ?string $operationDate): int
    {
        if ($operationDate === null || $schedule->planned_date === null) {
            return PHP_INT_MAX;
        }

        $planned = $schedule->planned_date->toDateString();

        return (int) abs((strtotime($planned) - strtotime($operationDate)) / 86400);
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
            'suggested_category_id' => $this->defaultCategoryId('payroll_managers'),
            'suggested_user_id' => $user->id,
            'suggested_candidates' => [],
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
     *     suggested_user_id: null,
     *     suggested_candidates: list<array<string, mixed>>
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
                    'suggested_candidates' => [],
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
                    'suggested_candidates' => [],
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
            'suggested_candidates' => [],
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

    private function suggestedCarrierCategoryId(?int $orderId, ?int $contractorId): ?int
    {
        return $this->costCategoryResolver->categoryIdForCarrier($orderId, $contractorId)
            ?? $this->defaultCategoryId(ManagementCostCategoryCodes::HIRED_TRANSPORT);
    }

    private function defaultCategoryId(string $code): ?int
    {
        return ManagementExpenseCategory::query()
            ->where('code', $code)
            ->value('id');
    }
}
