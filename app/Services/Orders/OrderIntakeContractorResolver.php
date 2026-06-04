<?php

namespace App\Services\Orders;

use App\Models\Contractor;
use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class OrderIntakeContractorResolver
{
    public function __construct(
        private readonly McpAccessGate $access,
    ) {}

    /**
     * @param  array<string, mixed>  $customer
     * @return list<array{id: int, name: string, inn: string|null, score: float, role: string}>
     */
    public function matchCustomer(User $user, array $customer): array
    {
        return $this->mapMatches($this->findByInn($user, (string) ($customer['inn'] ?? ''))
            ?: $this->findByName($user, (string) ($customer['name'] ?? '')), 'customer');
    }

    /**
     * @param  array<string, mixed>  $carrier
     * @return list<array{id: int, name: string, inn: string|null, score: float, role: string}>
     */
    public function matchCarrier(User $user, array $carrier): array
    {
        return $this->mapMatches($this->findByInn($user, (string) ($carrier['inn'] ?? ''))
            ?: $this->findByName($user, (string) ($carrier['name'] ?? ''), ['carrier', 'contractor', 'both']), 'carrier');
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $carrier
     * @return list<array{id: int, name: string, inn: string|null, score: float, role: string}>
     */
    public function matchParties(User $user, array $customer, array $carrier): array
    {
        $matches = [...$this->matchCustomer($user, $customer)];

        foreach ($this->matchCarrier($user, $carrier) as $carrierMatch) {
            $already = collect($matches)->contains(fn (array $row): bool => (int) $row['id'] === (int) $carrierMatch['id']);

            if (! $already) {
                $matches[] = $carrierMatch;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $ownCompany
     * @return array{id: int, name: string, inn: string|null, score: float}|null
     */
    public function matchOwnCompany(User $user, array $ownCompany): ?array
    {
        $inn = trim((string) ($ownCompany['inn'] ?? ''));
        if ($inn !== '' && preg_match('/^\d{10,12}$/', $inn) === 1) {
            $byInn = Contractor::query()
                ->where('is_own_company', true)
                ->where('inn', $inn)
                ->first(['id', 'name', 'inn']);

            if ($byInn !== null) {
                return [
                    'id' => $byInn->id,
                    'name' => (string) $byInn->name,
                    'inn' => $byInn->inn,
                    'score' => 1.0,
                ];
            }
        }

        $name = trim((string) ($ownCompany['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $candidates = Contractor::query()
            ->where('is_own_company', true)
            ->where(function (Builder $scoped) use ($name): void {
                $scoped->where('name', 'like', '%'.$name.'%');

                if ($this->hasFullNameColumn() && mb_strlen($name) >= 3) {
                    $scoped->orWhere('full_name', 'like', '%'.$name.'%');
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'inn']);

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $row) {
            $percent = 0.0;
            similar_text(mb_strtolower($name), mb_strtolower((string) $row->name), $percent);
            $score = round($percent / 100, 2);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'id' => $row->id,
                    'name' => (string) $row->name,
                    'inn' => $row->inn,
                    'score' => $score,
                ];
            }
        }

        return $bestScore >= 0.45 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return list<array{id: int, name: string, inn: string|null, score: float}>
     *
     * @deprecated Используйте matchCustomer / matchParties
     */
    public function match(User $user, array $customer): array
    {
        return array_map(
            fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'inn' => $row['inn'],
                'score' => $row['score'],
            ],
            $this->matchCustomer($user, $customer),
        );
    }

    /**
     * @param  list<Contractor>  $contractors
     * @return list<array{id: int, name: string, inn: string|null, score: float, role: string}>
     */
    private function mapMatches(array $contractors, string $role): array
    {
        return array_map(fn (array $row): array => [
            ...$row,
            'role' => $role,
        ], $contractors);
    }

    /**
     * @return list<array{id: int, name: string, inn: string|null, score: float}>
     */
    private function findByInn(User $user, string $inn): array
    {
        $inn = trim($inn);

        if ($inn === '' || preg_match('/^\d{10,12}$/', $inn) !== 1) {
            return [];
        }

        return $this->scopedQuery($user)
            ->where('inn', $inn)
            ->limit(3)
            ->get(['id', 'name', 'inn'])
            ->map(fn (Contractor $row): array => [
                'id' => $row->id,
                'name' => (string) $row->name,
                'inn' => $row->inn,
                'score' => 1.0,
            ])
            ->all();
    }

    /**
     * @param  list<string>|null  $types
     * @return list<array{id: int, name: string, inn: string|null, score: float}>
     */
    private function findByName(User $user, string $name, ?array $types = null): array
    {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        $builder = $this->scopedQuery($user)->where(function (Builder $scoped) use ($name): void {
            $scoped->where('name', 'like', '%'.$name.'%');

            if ($this->hasFullNameColumn() && mb_strlen($name) >= 3) {
                $scoped->orWhere('full_name', 'like', '%'.$name.'%');
            }
        });

        if ($types !== null && $types !== []) {
            $builder->whereIn('type', $types);
        }

        return $builder
            ->orderBy('name')
            ->limit(8)
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

        if (RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
            $this->access->applyContractorsScope($query, $user);
        }

        return $query;
    }

    private function hasFullNameColumn(): bool
    {
        return Schema::hasColumn('contractors', 'full_name');
    }
}
