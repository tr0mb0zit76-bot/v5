<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class KpiDeductionRule extends Model
{
    public const CUSTOM_RULES_CUTOFF_DATE = '2026-06-01';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'priority',
        'customer_payment_form',
        'customer_positive_vat_required',
        'customer_vat_rate_percent',
        'carrier_rule',
        'carrier_payment_forms',
        'carrier_vat_rate_percent',
        'deduction_primary_percent',
        'deduction_secondary_percent',
        'margin_supplement_percent',
        'margin_supplement_carrier_vat_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'customer_positive_vat_required' => 'boolean',
            'customer_vat_rate_percent' => 'decimal:2',
            'carrier_payment_forms' => 'array',
            'carrier_vat_rate_percent' => 'decimal:2',
            'deduction_primary_percent' => 'decimal:2',
            'deduction_secondary_percent' => 'decimal:2',
            'margin_supplement_percent' => 'decimal:2',
            'margin_supplement_carrier_vat_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Collection<int, self>
     */
    public static function activeForDate(string $date): Collection
    {
        if (! Schema::hasTable('kpi_deduction_rules') || blank($date)) {
            return collect();
        }

        return self::query()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get();
    }

    public static function usesCustomRulesOnDate(?string $date): bool
    {
        if (blank($date)) {
            return false;
        }

        return $date >= self::CUSTOM_RULES_CUTOFF_DATE;
    }
}
