<?php

namespace App\Services\Improvement;

/**
 * Двухпропорционный z-тест + Wilson CI для win rate A/B.
 * Не претендует на полный seq. analysis — early stop только как подсказка HITL.
 */
final class ImprovementAbStatistics
{
    public const DEFAULT_ALPHA = 0.05;

    public const DEFAULT_MDE_PP = 10.0;

    /**
     * @param  array{closed: int, won: int, lost?: int, win_rate_pct: float}  $a
     * @param  array{closed: int, won: int, lost?: int, win_rate_pct: float}  $b
     * @return array<string, mixed>
     */
    public function compareWinRates(array $a, array $b, float $alpha = self::DEFAULT_ALPHA, float $mdePp = self::DEFAULT_MDE_PP): array
    {
        $nA = max(0, (int) ($a['closed'] ?? 0));
        $nB = max(0, (int) ($b['closed'] ?? 0));
        $wA = max(0, (int) ($a['won'] ?? 0));
        $wB = max(0, (int) ($b['won'] ?? 0));

        $pA = $nA > 0 ? $wA / $nA : 0.0;
        $pB = $nB > 0 ? $wB / $nB : 0.0;
        $diffPp = round(($pB - $pA) * 100, 2);

        $ciA = $this->wilsonInterval($wA, $nA, $alpha);
        $ciB = $this->wilsonInterval($wB, $nB, $alpha);

        $z = null;
        $pValue = null;
        $significant = false;

        if ($nA >= 5 && $nB >= 5) {
            $pPool = ($wA + $wB) / max(1, $nA + $nB);
            $se = sqrt($pPool * (1 - $pPool) * (1 / $nA + 1 / $nB));
            if ($se > 0) {
                $z = ($pB - $pA) / $se;
                $pValue = $this->twoSidedPFromZ(abs($z));
                $significant = $pValue < $alpha;
            }
        }

        $perArm = $this->requiredSamplePerArm($mdePp, $alpha);
        $minN = min($nA, $nB);
        $powered = $minN >= $perArm;

        $earlyStopSuggested = $significant && $powered && $minN >= 20;

        $recommendation = 'collect_more';
        if ($earlyStopSuggested) {
            $recommendation = $diffPp > 0 ? 'suggest_adopt_b' : 'suggest_keep_a';
        } elseif ($powered && ! $significant) {
            $recommendation = 'likely_inconclusive';
        }

        return [
            'alpha' => $alpha,
            'mde_pp' => $mdePp,
            'diff_pp' => $diffPp,
            'z' => $z !== null ? round($z, 3) : null,
            'p_value' => $pValue !== null ? round($pValue, 4) : null,
            'significant' => $significant,
            'wilson_a' => $ciA,
            'wilson_b' => $ciB,
            'required_n_per_arm' => $perArm,
            'min_n' => $minN,
            'powered' => $powered,
            'early_stop_suggested' => $earlyStopSuggested,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Правило большого пальца: n на руку для MDE в п.п. при p≈0.3, alpha=0.05, power≈0.8.
     * n ≈ 16 * p*(1-p) / (mde)^2
     */
    public function requiredSamplePerArm(float $mdePp = self::DEFAULT_MDE_PP, float $alpha = self::DEFAULT_ALPHA): int
    {
        $mde = max(0.01, $mdePp / 100);
        $p = 0.3;
        $zAlpha = $alpha <= 0.01 ? 2.576 : 1.96;
        $zBeta = 0.84; // ~80% power
        $n = (($zAlpha + $zBeta) ** 2) * (2 * $p * (1 - $p)) / ($mde ** 2);

        return max(20, (int) ceil($n));
    }

    /**
     * @return array{low: float, high: float}
     */
    public function wilsonInterval(int $wins, int $n, float $alpha = self::DEFAULT_ALPHA): array
    {
        if ($n <= 0) {
            return ['low' => 0.0, 'high' => 0.0];
        }

        $z = $alpha <= 0.01 ? 2.576 : 1.96;
        $phat = $wins / $n;
        $z2 = $z * $z;
        $denom = 1 + $z2 / $n;
        $centre = $phat + $z2 / (2 * $n);
        $margin = $z * sqrt(($phat * (1 - $phat) + $z2 / (4 * $n)) / $n);

        return [
            'low' => round(max(0, ($centre - $margin) / $denom) * 100, 1),
            'high' => round(min(1, ($centre + $margin) / $denom) * 100, 1),
        ];
    }

    private function twoSidedPFromZ(float $absZ): float
    {
        // Abramowitz & Stegun approximation for erfc / normal CDF tail
        $t = 1 / (1 + 0.2316419 * $absZ);
        $d = 0.3989423 * exp(-$absZ * $absZ / 2);
        $prob = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        return min(1.0, max(0.0, 2 * $prob));
    }
}
