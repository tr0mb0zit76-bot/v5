<?php

declare(strict_types=1);

namespace App\Services\LoadBoard;

use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use Illuminate\Support\Collection;

final class LoadBoardAdvisorService
{
    public function __construct(
        private readonly LoadBoardRateObservationService $rateObservations,
    ) {}

    /**
     * @return array{
     *     risk_level: string,
     *     risk_score: int,
     *     risk_factors: list<array{code: string, label: string, severity: string}>,
     *     summary: string,
     *     ranked_offers: list<array<string, mixed>>,
     *     corridor_insights: array<string, mixed>|null,
     *     carrier_pool: array<string, mixed>
     * }
     */
    public function advise(LoadBoardPost $post, ?LoadBoardCarrierPoolService $carrierPool = null): array
    {
        $carrierPool ??= app(LoadBoardCarrierPoolService::class);

        /** @var Collection<int, LoadBoardOffer> $offers */
        $offers = $post->relationLoaded('offers')
            ? $post->offers
            : $post->offers()->with('carrier:id,name')->get();

        $insights = $this->rateObservations->corridorInsightsForPost($post);
        $risks = $this->assessRisks($post, $offers);
        $ranked = $this->rankOffers($post, $offers, $insights);
        $pool = $carrierPool->forPost($post);

        return [
            'risk_level' => $risks['level'],
            'risk_score' => $risks['score'],
            'risk_factors' => $risks['factors'],
            'summary' => $this->buildSummary($post, $ranked, $risks, $insights),
            'ranked_offers' => $ranked,
            'corridor_insights' => $insights,
            'carrier_pool' => $pool,
        ];
    }

    /**
     * @param  Collection<int, LoadBoardOffer>  $offers
     * @return array{level: string, score: int, factors: list<array{code: string, label: string, severity: string}>}
     */
    private function assessRisks(LoadBoardPost $post, Collection $offers): array
    {
        $factors = [];
        $score = 0;

        if (! in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            if ($offers->isEmpty()) {
                $factors[] = [
                    'code' => 'no_offers',
                    'label' => 'Нет офферов — перевозчик не найден',
                    'severity' => 'high',
                ];
                $score += 35;
            }

            if ($post->buyer_id === null && in_array($post->status, ['new', 'in_work'], true)) {
                $factors[] = [
                    'code' => 'no_buyer',
                    'label' => 'Закупщик не назначен',
                    'severity' => 'medium',
                ];
                $score += 15;
            }

            if ($post->priority === 'urgent') {
                $factors[] = [
                    'code' => 'urgent',
                    'label' => 'Срочный груз — нужен быстрый выбор перевозчика',
                    'severity' => 'medium',
                ];
                $score += 10;
            }
        }

        if ($post->loading_date !== null) {
            $loading = $post->loading_date->startOfDay();
            $today = now()->startOfDay();
            $daysUntil = (int) $today->diffInDays($loading, false);

            if ($daysUntil < 0) {
                $factors[] = [
                    'code' => 'loading_overdue',
                    'label' => 'Дата погрузки уже прошла',
                    'severity' => 'high',
                ];
                $score += 30;
            } elseif ($daysUntil <= 2) {
                $factors[] = [
                    'code' => 'loading_soon',
                    'label' => 'Погрузка в ближайшие 2 дня',
                    'severity' => 'high',
                ];
                $score += 25;
            } elseif ($daysUntil <= 5) {
                $factors[] = [
                    'code' => 'loading_week',
                    'label' => 'Погрузка в течение недели',
                    'severity' => 'medium',
                ];
                $score += 10;
            }
        }

        $customerRate = $post->customer_rate !== null ? (float) $post->customer_rate : null;
        if ($customerRate !== null && $customerRate > 0 && $offers->isNotEmpty()) {
            $bestMargin = $offers
                ->map(fn (LoadBoardOffer $offer): ?float => $this->rateObservations->marginPreview($customerRate, (float) $offer->carrier_rate)['pct'])
                ->filter(fn (?float $value): bool => $value !== null)
                ->max();

            if ($bestMargin !== null && $bestMargin < 3) {
                $factors[] = [
                    'code' => 'low_margin',
                    'label' => 'Лучший оффер даёт маржу ниже 3%',
                    'severity' => 'medium',
                ];
                $score += 20;
            }
        }

        if ($post->published_at !== null && ! in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            $daysOpen = (int) $post->published_at->diffInDays(now());
            if ($daysOpen >= 5 && $offers->count() < 2) {
                $factors[] = [
                    'code' => 'stale',
                    'label' => 'Груз открыт '.$daysOpen.' дн. — мало вариантов',
                    'severity' => 'medium',
                ];
                $score += 15;
            }
        }

        $score = min(100, $score);

        return [
            'level' => match (true) {
                $score >= 55 => 'high',
                $score >= 25 => 'medium',
                default => 'low',
            },
            'score' => $score,
            'factors' => $factors,
        ];
    }

    /**
     * @param  Collection<int, LoadBoardOffer>  $offers
     * @param  array<string, mixed>|null  $insights
     * @return list<array<string, mixed>>
     */
    private function rankOffers(LoadBoardPost $post, Collection $offers, ?array $insights): array
    {
        $customerRate = $post->customer_rate !== null ? (float) $post->customer_rate : null;
        $targetRate = $post->target_carrier_rate !== null ? (float) $post->target_carrier_rate : null;
        $corridorAvg = $insights['carrier_rate']['avg'] ?? null;

        $ranked = $offers
            ->reject(fn (LoadBoardOffer $offer): bool => $offer->status === 'rejected')
            ->map(function (LoadBoardOffer $offer) use ($customerRate, $targetRate, $corridorAvg): array {
                $rate = (float) $offer->carrier_rate;
                $margin = $this->rateObservations->marginPreview($customerRate, $rate);
                $score = 50.0;

                if ($margin['pct'] !== null) {
                    $score += min(25.0, (float) $margin['pct'] * 1.5);
                }

                if ($targetRate !== null && $rate <= $targetRate) {
                    $score += 15.0;
                }

                if ($corridorAvg !== null) {
                    if ($rate <= (float) $corridorAvg) {
                        $score += 10.0;
                    } else {
                        $score -= min(15.0, (($rate - (float) $corridorAvg) / max(1.0, (float) $corridorAvg)) * 20.0);
                    }
                }

                $score += match ($offer->status) {
                    'approved' => 20.0,
                    'selected' => 12.0,
                    default => 0.0,
                };

                $reasons = [];
                if ($targetRate !== null && $rate <= $targetRate) {
                    $reasons[] = 'В целевой себестоимости';
                }
                if ($margin['pct'] !== null && $margin['pct'] >= 10) {
                    $reasons[] = 'Хорошая маржа';
                }
                if ($corridorAvg !== null && $rate <= (float) $corridorAvg) {
                    $reasons[] = 'Ниже среднего по коридору';
                }
                if ($reasons === []) {
                    $reasons[] = 'Сравните условия и сроки';
                }

                return [
                    'offer_id' => $offer->id,
                    'score' => (int) round(max(0, min(100, $score))),
                    'reasons' => $reasons,
                    'margin_abs' => $margin['abs'],
                    'margin_pct' => $margin['pct'],
                    'carrier_rate' => $rate,
                    'carrier_name' => $offer->carrier?->name,
                    'source' => $offer->source,
                    'status' => $offer->status,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();

        return $ranked;
    }

    /**
     * @param  list<array<string, mixed>>  $ranked
     * @param  array{level: string, score: int, factors: list<array<string, mixed>>}  $risks
     * @param  array<string, mixed>|null  $insights
     */
    private function buildSummary(LoadBoardPost $post, array $ranked, array $risks, ?array $insights): string
    {
        if (in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            return 'Кейс закрыт — советник не меняет решение.';
        }

        $parts = [];

        if ($ranked !== []) {
            $top = $ranked[0];
            $name = $top['carrier_name'] ?? 'перевозчик #'.$top['offer_id'];
            $parts[] = 'Лучший ориентир: '.$name.' (оценка '.$top['score'].'/100)';
        } else {
            $parts[] = 'Добавьте офферы или кандидатов в пул';
        }

        if (($insights['available'] ?? false) && isset($insights['carrier_rate']['avg'])) {
            $parts[] = 'Средняя ставка по коридору ~ '.number_format((float) $insights['carrier_rate']['avg'], 0, ',', ' ').' ₽';
        }

        if ($risks['factors'] !== []) {
            $parts[] = 'Риск: '.$risks['factors'][0]['label'];
        }

        return implode('. ', $parts).'.';
    }
}
