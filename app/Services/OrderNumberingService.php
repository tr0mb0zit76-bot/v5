<?php

namespace App\Services;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderNumberingRule;
use App\Models\User;
use App\Support\ManagerInitialsResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderNumberingService
{
    public function findRuleForOwnCompany(?int $ownCompanyId): ?OrderNumberingRule
    {
        if ($ownCompanyId === null || $ownCompanyId <= 0 || ! Schema::hasTable('order_numbering_rules')) {
            return null;
        }

        return OrderNumberingRule::query()
            ->where('own_company_id', $ownCompanyId)
            ->first();
    }

    /**
     * @return array{order_number: string, company_code: string, cipher: string|null, preview: bool}
     */
    public function preview(?Contractor $ownCompany, ?CarbonInterface $at = null, ?User $manager = null): array
    {
        $rule = $this->findRuleForOwnCompany($ownCompany !== null ? (int) $ownCompany->id : null);

        if ($rule === null) {
            return [
                ...app(OrderNumberGenerator::class)->generate($ownCompany, $manager),
                'cipher' => null,
                'preview' => true,
            ];
        }

        $at ??= now();
        $sequence = $this->peekNextSequence($rule, $at);

        return [
            'order_number' => $this->composeNumber($rule, $sequence, $at, $manager),
            'company_code' => $this->resolveCompanyCode($rule),
            'cipher' => $rule->cipher,
            'preview' => true,
        ];
    }

    /**
     * @return array{order_number: string, company_code: string, cipher: string|null}
     */
    public function generateAndReserve(?Contractor $ownCompany, ?CarbonInterface $at = null, ?User $manager = null): array
    {
        $rule = $this->findRuleForOwnCompany($ownCompany !== null ? (int) $ownCompany->id : null);

        if ($rule === null) {
            $legacy = app(OrderNumberGenerator::class)->generate($ownCompany, $manager);

            return [
                ...$legacy,
                'cipher' => null,
            ];
        }

        $at ??= now();

        return DB::transaction(function () use ($rule, $at, $manager): array {
            $locked = OrderNumberingRule::query()
                ->whereKey($rule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence = $this->reserveNextSequence($locked, $at);
            $orderNumber = $this->composeNumber($locked, $sequence, $at, $manager);

            // На случай дыр/ручных правок: не отдаём номер, который уже есть у живого заказа.
            for ($attempt = 0; $attempt < 30; $attempt++) {
                if (! $this->orderNumberTaken($orderNumber)) {
                    break;
                }

                $sequence = $this->reserveNextSequence($locked, $at);
                $orderNumber = $this->composeNumber($locked, $sequence, $at, $manager);
            }

            return [
                'order_number' => $orderNumber,
                'company_code' => $this->resolveCompanyCode($locked),
                'cipher' => $locked->cipher,
            ];
        });
    }

    /**
     * Выдача номера при создании заказа.
     * Превью из UI игнорируется (два таба иначе получают один номер); ручной ввод — только с флагом.
     *
     * @return array{order_number: string, company_code: string, cipher: string|null}
     */
    public function allocateForCreate(
        ?Contractor $ownCompany,
        ?User $manager = null,
        ?string $requestedNumber = null,
        bool $manualOverride = false,
        ?CarbonInterface $at = null,
    ): array {
        $rule = $this->findRuleForOwnCompany($ownCompany !== null ? (int) $ownCompany->id : null);
        $at ??= now();
        $requested = is_string($requestedNumber) ? trim($requestedNumber) : '';

        if ($rule === null) {
            if ($requested !== '' && $manualOverride) {
                if ($this->orderNumberTaken($requested)) {
                    throw ValidationException::withMessages([
                        'order_number' => 'Такой номер заказа уже занят.',
                    ]);
                }

                return [
                    'order_number' => $requested,
                    'company_code' => app(OrderNumberGenerator::class)->resolveCompanyCodeOnly($ownCompany),
                    'cipher' => null,
                ];
            }

            return [
                ...app(OrderNumberGenerator::class)->generate($ownCompany, $manager),
                'cipher' => null,
            ];
        }

        if ($manualOverride && $requested !== '') {
            return DB::transaction(function () use ($rule, $at, $requested): array {
                $locked = OrderNumberingRule::query()
                    ->whereKey($rule->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->orderNumberTaken($requested)) {
                    throw ValidationException::withMessages([
                        'order_number' => 'Такой номер заказа уже занят.',
                    ]);
                }

                $this->bumpCounterToAssigned($locked, $at, $requested);

                return [
                    'order_number' => $requested,
                    'company_code' => $this->resolveCompanyCode($locked),
                    'cipher' => $locked->cipher,
                ];
            });
        }

        return $this->generateAndReserve($ownCompany, $at, $manager);
    }

    public function composeNumber(OrderNumberingRule $rule, int $sequence, CarbonInterface $at, ?User $manager = null): string
    {
        $separator = $this->normalizeSeparator($rule->separator);
        $parts = [
            $this->renderSegment($rule->prefix_type, $rule->prefix_value, $sequence, $at, $rule->sequence_pad, $manager),
            $this->renderSegment($rule->body_type, $rule->body_value, $sequence, $at, $rule->sequence_pad, $manager),
            $this->renderSegment($rule->suffix_type, $rule->suffix_value, $sequence, $at, $rule->sequence_pad, $manager),
        ];

        return collect($parts)
            ->filter(fn (?string $part): bool => $part !== null && $part !== '')
            ->implode($separator);
    }

    public function resolveCompanyCode(OrderNumberingRule $rule): string
    {
        $cipher = Str::upper(Str::substr(preg_replace('/[^\p{L}\p{N}]/u', '', $rule->cipher) ?? '', 0, 10));

        return $cipher !== '' ? $cipher : 'ORD';
    }

    private function renderSegment(
        OrderNumberSegmentType $type,
        ?string $value,
        int $sequence,
        CarbonInterface $at,
        int $pad,
        ?User $manager = null,
    ): string {
        return match ($type) {
            OrderNumberSegmentType::Text => trim((string) $value),
            OrderNumberSegmentType::Sequence => $this->formatSequence($sequence, $pad),
            OrderNumberSegmentType::Day => $at->format('d'),
            OrderNumberSegmentType::Month => $at->format('m'),
            OrderNumberSegmentType::ManagerInitials => ManagerInitialsResolver::fromUser($manager),
        };
    }

    private function formatSequence(int $sequence, int $pad): string
    {
        if ($pad > 0) {
            return str_pad((string) max(1, $sequence), $pad, '0', STR_PAD_LEFT);
        }

        return (string) max(1, $sequence);
    }

    private function normalizeSeparator(string $separator): string
    {
        $trimmed = trim($separator);

        return $trimmed !== '' ? $trimmed : '-';
    }

    private function scopeKey(OrderNumberSequenceScope $scope, CarbonInterface $at): string
    {
        return match ($scope) {
            OrderNumberSequenceScope::Global => 'global',
            OrderNumberSequenceScope::Year => $at->format('Y'),
            OrderNumberSequenceScope::Month => $at->format('Y-m'),
        };
    }

    private function peekNextSequence(OrderNumberingRule $rule, CarbonInterface $at): int
    {
        $key = $this->scopeKey($rule->sequence_scope, $at);
        $counters = is_array($rule->sequence_counters) ? $rule->sequence_counters : [];

        return ((int) ($counters[$key] ?? 0)) + 1;
    }

    private function reserveNextSequence(OrderNumberingRule $rule, CarbonInterface $at): int
    {
        $key = $this->scopeKey($rule->sequence_scope, $at);
        $counters = is_array($rule->sequence_counters) ? $rule->sequence_counters : [];
        $next = ((int) ($counters[$key] ?? 0)) + 1;
        $counters[$key] = $next;
        $rule->sequence_counters = $counters;
        $rule->save();

        return $next;
    }

    /**
     * Подтянуть счётчик до выданного вручную/из превью номера, без лишнего +1.
     * Нужно при create с уже заполненным order_number, чтобы следующий reserve не выдал дубль.
     */
    public function acknowledgeAssignedSequence(?Contractor $ownCompany, string $orderNumber, ?CarbonInterface $at = null): void
    {
        $rule = $this->findRuleForOwnCompany($ownCompany !== null ? (int) $ownCompany->id : null);

        if ($rule === null) {
            return;
        }

        if (! preg_match('/(\d+)\s*$/u', $orderNumber, $matches)) {
            return;
        }

        $assigned = (int) $matches[1];
        if ($assigned < 1) {
            return;
        }

        $at ??= now();

        DB::transaction(function () use ($rule, $at, $assigned): void {
            $locked = OrderNumberingRule::query()
                ->whereKey($rule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->bumpCounterToAssigned($locked, $at, (string) $assigned);
        });
    }

    private function orderNumberTaken(string $orderNumber): bool
    {
        $query = Order::query()->where('order_number', $orderNumber);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function bumpCounterToAssigned(OrderNumberingRule $rule, CarbonInterface $at, string $orderNumberOrSequence): void
    {
        if (preg_match('/(\d+)\s*$/u', $orderNumberOrSequence, $matches) !== 1) {
            if (! ctype_digit($orderNumberOrSequence)) {
                return;
            }

            $assigned = (int) $orderNumberOrSequence;
        } else {
            $assigned = (int) $matches[1];
        }

        if ($assigned < 1) {
            return;
        }

        $key = $this->scopeKey($rule->sequence_scope, $at);
        $counters = is_array($rule->sequence_counters) ? $rule->sequence_counters : [];
        $current = (int) ($counters[$key] ?? 0);

        if ($assigned <= $current) {
            return;
        }

        $counters[$key] = $assigned;
        $rule->sequence_counters = $counters;
        $rule->save();
    }
}
