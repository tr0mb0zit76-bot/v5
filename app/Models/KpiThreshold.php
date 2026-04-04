<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class KpiThreshold extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'deal_type',
        'threshold_from',
        'threshold_to',
        'kpi_percent',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_from' => 'decimal:2',
            'threshold_to' => 'decimal:2',
            'kpi_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Collection<int, self>
     */
    public static function active(): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('threshold_from')
            ->orderBy('deal_type')
            ->get();
    }
}
