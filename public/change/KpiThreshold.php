<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiThreshold extends Model
{
    protected $table = 'kpi_thresholds';

    protected $fillable = [
        'deal_type',
        'threshold_from',
        'threshold_to',
        'kpi_percent',
        'is_active',
    ];

    protected $casts = [
        'threshold_from' => 'decimal:2',
        'threshold_to' => 'decimal:2',
        'kpi_percent' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Проверка, попадает ли значение в диапазон
     */
    public function contains(float $value): bool
    {
        return $value >= $this->threshold_from && $value <= $this->threshold_to;
    }

    /**
     * Получить все активные пороги
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('threshold_from', 'desc')
            ->get();
    }

    /**
     * Получить пороги, сгруппированные по диапазонам
     */
    public static function getGroupedByRange()
    {
        $thresholds = [];

        $ranges = self::select('threshold_from', 'threshold_to')
            ->where('is_active', true)
            ->distinct()
            ->orderBy('threshold_from', 'desc')
            ->get();

        foreach ($ranges as $range) {
            $direct = self::where('deal_type', 'direct')
                ->where('threshold_from', $range->threshold_from)
                ->where('threshold_to', $range->threshold_to)
                ->first();

            $indirect = self::where('deal_type', 'indirect')
                ->where('threshold_from', $range->threshold_from)
                ->where('threshold_to', $range->threshold_to)
                ->first();

            $thresholds[] = [
                'from' => $range->threshold_from,
                'to' => $range->threshold_to,
                'direct' => $direct,
                'indirect' => $indirect,
            ];
        }

        return $thresholds;
    }
}
