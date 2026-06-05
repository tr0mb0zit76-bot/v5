<?php

namespace App\Services;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use App\Models\Contractor;
use App\Models\OrderNumberingRule;
use App\Models\User;
use App\Support\ManagerInitialsResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

            return [
                'order_number' => $this->composeNumber($locked, $sequence, $at, $manager),
                'company_code' => $this->resolveCompanyCode($locked),
                'cipher' => $locked->cipher,
            ];
        });
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
}
