<?php

namespace App\Services\ManagementAccounting;

use App\Models\Contractor;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Support\ManagementCostCategoryCodes;
use App\Support\PaymentScheduleSettlementStatus;
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

        $category = $this->matchCategoryByKeywords($description, $direction);
        if ($category !== null) {
            return $category;
        }

        return $this->directionFallbackCategory($direction);
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

        if ($candidates->isEmpty()) {
            $candidates = $this->operationalCandidatesByContractorInDescription(
                $description,
                $line->direction,
                (float) $line->amount,
                $operationDate,
            );
        }

        if ($candidates->isEmpty()) {
            $candidates = $this->operationalCandidatesByAmountOnly(
                $description,
                $line->direction,
                (float) $line->amount,
                $operationDate,
            );
        }

        return $this->serializeOperationalCandidates(
            $this->sortOperationalCandidateCollection($candidates, (float) $line->amount, $operationDate),
        );
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

        $candidates = $candidates->merge(
            $this->operationalCandidatesByContractorInDescription(
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
        } elseif ($candidates->isEmpty()) {
            $candidates = $this->operationalCandidatesByAmountOnly(
                $description,
                $line->direction,
                (float) $line->amount,
                $operationDate,
            );
        }

        $candidates = $this->sortOperationalCandidateCollection(
            $candidates
                ->unique(fn (array $candidate): int => (int) $candidate['schedule']->id)
                ->values(),
            (float) $line->amount,
            $operationDate,
        );

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
                'suggested_category_id' => $this->suggestedOperationalCategoryId($direction, null, null),
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
            'suggested_category_id' => $this->suggestedOperationalCategoryId(
                $direction,
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
                    'amount' => (float) $schedule->amount,
                    'amount_due' => $this->effectiveScheduleAmount($schedule),
                    'paid_amount' => (float) ($schedule->paid_amount ?? 0),
                    'contractor_label' => $candidate['contractor_label'],
                    'party' => $schedule->party,
                    'match_reason' => $candidate['match_reason'] ?? null,
                    'match_reason_label' => $this->matchReasonLabel($candidate['match_reason'] ?? null),
                    'slot_label' => $this->scheduleSlotLabel($schedule),
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
            ->with($this->operationalScheduleEagerLoads())
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
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey($candidate, $amount, $operationDate))
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float, match_reason: string}>
     */
    private function operationalCandidatesByAmountOnly(
        string $description,
        string $direction,
        float $amount,
        ?string $operationDate,
    ): Collection {
        $parties = $direction === 'in' ? ['customer'] : ['carrier', 'contractor'];

        return $this->openOperationalSchedulesQuery()
            ->with($this->operationalScheduleEagerLoads())
            ->whereIn('party', $parties)
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentSchedule $schedule): bool => $this->amountMatchesSchedule($schedule, $amount))
            ->map(function (PaymentSchedule $schedule) use ($description, $direction, $operationDate, $amount): array {
                $contractors = $this->contractorsForSchedule($schedule, $direction);
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
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey(
                $candidate,
                $amount,
                $operationDate,
                $candidate['match_reason'] === 'amount_only' ? 1 : 0,
            ))
            ->take(20)
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: PaymentSchedule, contractor_label: string, order_number: ?string, date_distance: int, amount_distance: float, match_reason: string}>
     */
    private function operationalCandidatesByContractorInDescription(
        string $description,
        string $direction,
        float $amount,
        ?string $operationDate,
    ): Collection {
        $parties = $direction === 'in' ? ['customer'] : ['carrier', 'contractor'];

        return $this->openOperationalSchedulesQuery()
            ->with($this->operationalScheduleEagerLoads())
            ->whereIn('party', $parties)
            ->orderBy('id')
            ->get()
            ->map(function (PaymentSchedule $schedule) use ($description, $direction, $operationDate, $amount): ?array {
                $matchedContractor = null;

                foreach ($this->contractorsForSchedule($schedule, $direction) as $contractor) {
                    if ($this->contractorLabelInDescription($description, $contractor)) {
                        $matchedContractor = $contractor;

                        break;
                    }
                }

                if ($matchedContractor === null) {
                    return null;
                }

                if ($amount > $this->effectiveScheduleAmount($schedule) + 0.01) {
                    return null;
                }

                return [
                    'schedule' => $schedule,
                    'contractor_label' => $this->contractorDisplayLabel($matchedContractor),
                    'order_number' => $schedule->order?->order_number,
                    'date_distance' => $this->plannedDateDistanceDays($schedule, $operationDate),
                    'amount_distance' => abs($this->effectiveScheduleAmount($schedule) - $amount),
                    'match_reason' => 'contractor_relaxed',
                ];
            })
            ->filter()
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey($candidate, $amount, $operationDate))
            ->take(10)
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
            ->with($this->operationalScheduleEagerLoads())
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
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey($candidate, $amount, $operationDate))
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
            ->with($this->operationalScheduleEagerLoads())
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
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey($candidate, $amount, $operationDate))
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
        return $this->openOperationalSchedulesQuery()
            ->where('order_id', $order->id)
            ->where('party', $party)
            ->get()
            ->filter(fn (PaymentSchedule $schedule): bool => $this->amountMatchesSchedule($schedule, $amount))
            ->sortBy(fn (PaymentSchedule $schedule): array => $this->scheduleCandidateSortKey($schedule, $amount, $operationDate))
            ->first();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function scheduleCandidateSortKey(PaymentSchedule $schedule, float $amount, ?string $operationDate): array
    {
        return [
            abs($this->effectiveScheduleAmount($schedule) - $amount) >= 0.01 ? 1 : 0,
            $this->scheduleSlotSequence($schedule),
            $this->plannedDateDistanceDays($schedule, $operationDate),
            $schedule->id,
        ];
    }

    /**
     * @param  array{schedule: PaymentSchedule, date_distance?: int, amount_distance?: float, match_reason?: string}  $candidate
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function candidateSortKey(array $candidate, float $amount, ?string $operationDate, int $prefixRank = 0): array
    {
        $schedule = $candidate['schedule'];

        return [
            $prefixRank,
            ($candidate['amount_distance'] ?? abs($this->effectiveScheduleAmount($schedule) - $amount)) >= 0.01 ? 1 : 0,
            $this->scheduleSlotSequence($schedule),
            $candidate['date_distance'] ?? $this->plannedDateDistanceDays($schedule, $operationDate),
            $schedule->id,
        ];
    }

    private function scheduleSlotSequence(PaymentSchedule $schedule): int
    {
        if (Schema::hasColumn('payment_schedules', 'installment_sequence') && $schedule->installment_sequence !== null) {
            return (int) $schedule->installment_sequence;
        }

        if (Schema::hasColumn('payment_schedules', 'type')) {
            return match ((string) $schedule->type) {
                'prepayment' => 1,
                'installment' => 50,
                'final' => 100,
                default => 200,
            };
        }

        return 200;
    }

    private function scheduleSlotLabel(PaymentSchedule $schedule): ?string
    {
        if (! Schema::hasColumn('payment_schedules', 'type')) {
            return null;
        }

        return match ((string) $schedule->type) {
            'prepayment' => 'Предоплата',
            'final' => 'Финальный платёж',
            'installment' => 'Транш',
            default => null,
        };
    }

    private function amountMatchesSchedule(PaymentSchedule $schedule, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $openAmount = $this->effectiveScheduleAmount($schedule);

        if ($openAmount <= 0.009) {
            return false;
        }

        if (abs($openAmount - $amount) < 0.01) {
            return true;
        }

        return $amount < $openAmount;
    }

    private function effectiveScheduleAmount(PaymentSchedule $schedule): float
    {
        return PaymentScheduleSettlementStatus::outstandingAmount(
            (float) $schedule->amount,
            (float) ($schedule->paid_amount ?? 0),
            $schedule->remaining_amount !== null ? (float) $schedule->remaining_amount : null,
        );
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
            'contractor_relaxed' => 'контрагент в назначении',
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
            $normalizedLabel = $this->normalizeContractorLabelForMatch($label);
            if (mb_strlen($normalizedLabel) >= 5 && str_contains($description, $normalizedLabel)) {
                return true;
            }

            $distinctive = $this->normalizeContractorLabelForMatch($this->stripLegalFormPrefix($label));
            if ($this->descriptionContainsDistinctiveName($description, $distinctive)) {
                return true;
            }

            if ($this->descriptionContainsReversedLegalFormName($description, $label)) {
                return true;
            }
        }

        foreach ($this->contractorSearchTokens($contractor) as $token) {
            if ($this->descriptionContainsDistinctiveName($description, $token)) {
                return true;
            }

            if (mb_strlen($token) >= 4 && str_contains($description, 'тк '.$token)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeContractorLabelForMatch(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = str_replace(['«', '»', '"', "'"], '', $label);

        return trim((string) preg_replace('/\s+/u', ' ', $label));
    }

    private function descriptionContainsDistinctiveName(string $description, string $distinctive): bool
    {
        $distinctive = $this->normalizeContractorLabelForMatch($distinctive);
        if ($distinctive === '') {
            return false;
        }

        $minimumLength = mb_strlen($distinctive) <= 4 ? 3 : 4;
        if (mb_strlen($distinctive) < $minimumLength) {
            return false;
        }

        $pattern = '/(?:^|[\s\/"«»,\.\(\)]+)'
            .preg_quote($distinctive, '/')
            .'(?:$|[\s\/"«»,\.\(\)]+)/u';

        return preg_match($pattern, $description) === 1;
    }

    private function descriptionContainsReversedLegalFormName(string $description, string $label): bool
    {
        $normalized = $this->normalizeContractorLabelForMatch($label);
        $distinctive = $this->normalizeContractorLabelForMatch($this->stripLegalFormPrefix($normalized));

        if (! $this->descriptionContainsDistinctiveName($description, $distinctive)) {
            return false;
        }

        if (preg_match('/^(?:ооо|оао|зао|пао|ип|ао|чп)\s+/u', $normalized) !== 1) {
            return false;
        }

        return (bool) preg_match('/(?:^|[\s\/])+(?:ооо|оао|зао|пао|ип|ао|чп)(?:[\s\/]|$)/u', $description);
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

        $distinctive = $this->normalizeContractorLabelForMatch(
            $this->stripLegalFormPrefix(mb_strtolower(trim((string) $contractor->name))),
        );
        if (mb_strlen($distinctive) >= 3 && mb_strlen($distinctive) <= 4) {
            $tokens[] = $distinctive;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  Collection<int, array{schedule: PaymentSchedule, date_distance?: int, amount_distance?: float, match_reason?: string}>  $candidates
     * @return Collection<int, array{schedule: PaymentSchedule, date_distance?: int, amount_distance?: float, match_reason?: string}>
     */
    private function sortOperationalCandidateCollection(Collection $candidates, float $amount, ?string $operationDate): Collection
    {
        return $candidates
            ->sortBy(fn (array $candidate): array => $this->candidateSortKey(
                $candidate,
                $amount,
                $operationDate,
                match ($candidate['match_reason'] ?? null) {
                    'contractor', 'contractor_relaxed', 'invoice' => 0,
                    'search' => 1,
                    'amount_only' => 2,
                    default => 1,
                },
            ))
            ->values();
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

        foreach ($this->invoiceReferencesFromDescription($description) as $reference) {
            if ($this->invoiceNumbersEquivalent($reference, $invoiceNumber)) {
                return true;
            }
        }

        $invoiceDigits = preg_replace('/\D+/', '', $invoice) ?? '';
        if ($invoiceDigits === '' || strlen($invoiceDigits) < 3) {
            return false;
        }

        $descDigits = preg_replace('/\D+/', '', $desc) ?? '';

        return str_contains($descDigits, $invoiceDigits);
    }

    /**
     * @return list<string>
     */
    private function invoiceReferencesFromDescription(string $description): array
    {
        $references = [];

        if (preg_match_all('/(?:сч(?:ет|\.)|\bсч\b)\s*\.?\s*(?:№|#|n|no\.?)?\s*(\d+)/ui', $description, $matches) > 0) {
            foreach ($matches[1] as $digits) {
                $digits = trim((string) $digits);
                if ($digits !== '') {
                    $references[] = $digits;
                }
            }
        }

        return array_values(array_unique($references));
    }

    private function invoiceNumbersEquivalent(string $reference, string $invoiceNumber): bool
    {
        $reference = mb_strtolower(trim($reference));
        $invoice = mb_strtolower(trim($invoiceNumber));

        if ($reference === '' || $invoice === '') {
            return false;
        }

        if ($reference === $invoice || str_contains($invoice, $reference)) {
            return true;
        }

        $referenceDigits = preg_replace('/\D+/', '', $reference) ?? '';
        $invoiceDigits = preg_replace('/\D+/', '', $invoice) ?? '';

        if ($referenceDigits === '' || $invoiceDigits === '') {
            return false;
        }

        return $referenceDigits === $invoiceDigits
            || str_ends_with($invoiceDigits, $referenceDigits);
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

        return null;
    }

    /**
     * @return array{
     *     match_type: ?string,
     *     match_confidence: int,
     *     match_notes: ?string,
     *     suggested_order_id: null,
     *     suggested_payment_schedule_id: null,
     *     suggested_category_id: ?int,
     *     suggested_user_id: null,
     *     suggested_candidates: list<array<string, mixed>>
     * }
     */
    private function directionFallbackCategory(string $direction): array
    {
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

    private function suggestedOperationalCategoryId(string $direction, ?int $orderId, ?int $contractorId): ?int
    {
        if ($direction === 'in') {
            return $this->defaultCategoryId('operational_customer_in');
        }

        return $this->suggestedCarrierCategoryId($orderId, $contractorId);
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

    /**
     * @return array<int, string>
     */
    private function operationalScheduleEagerLoads(): array
    {
        $orderColumns = ['id', 'order_number', 'customer_id', 'carrier_id'];

        if (Schema::hasColumn('orders', 'performers')) {
            $orderColumns[] = 'performers';
        }

        return [
            'order:'.implode(',', $orderColumns),
            'order.client:id,name,full_name',
            'order.carrier:id,name,full_name',
            'counterparty:id,name,full_name',
        ];
    }
}
