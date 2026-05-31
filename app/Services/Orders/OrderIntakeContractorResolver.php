<?php

namespace App\Services\Orders;

use App\Models\Contractor;
use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use Illuminate\Database\Eloquent\Builder;

class OrderIntakeContractorResolver
{
    public function __construct(
        private readonly McpAccessGate $access,
    ) {}

    /**
     * @param  array<string, mixed>  $customer
     * @return list<array{id: int, name: string, inn: string|null, score: float}>
     */
    public function match(User $user, array $customer): array
    {
        $this->access->requireContractorsArea($user);

        $inn = trim((string) ($customer['inn'] ?? ''));
        if ($inn !== '' && preg_match('/^\d{10,12}$/', $inn) === 1) {
            $byInn = $this->scopedQuery($user)
                ->where('inn', $inn)
                ->limit(3)
                ->get(['id', 'name', 'inn']);

            if ($byInn->isNotEmpty()) {
                return $byInn->map(fn (Contractor $row): array => [
                    'id' => $row->id,
                    'name' => (string) $row->name,
                    'inn' => $row->inn,
                    'score' => 1.0,
                ])->all();
            }
        }

        $name = trim((string) ($customer['name'] ?? ''));
        if ($name === '') {
            return [];
        }

        return $this->scopedQuery($user)
            ->where('name', 'like', '%'.$name.'%')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'inn'])
            ->map(function (Contractor $row) use ($name): array {
                $percent = 0.0;
                similar_text(mb_strtolower($name), mb_strtolower((string) $row->name), $percent);

                return [
                    'id' => $row->id,
                    'name' => (string) $row->name,
                    'inn' => $row->inn,
                    'score' => round($percent / 100, 2),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @return Builder<Contractor>
     */
    private function scopedQuery(User $user): Builder
    {
        $query = Contractor::query();
        $this->access->applyContractorsScope($query, $user);

        return $query;
    }
}
