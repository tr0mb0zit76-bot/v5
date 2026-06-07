<?php

namespace App\Services\Commercial;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class MailMailboxUserResolver
{
    /**
     * @return list<array{user_id: int, name: string, email: string|null}>
     */
    public function findCandidates(string $query, int $limit = 5): array
    {
        $needle = trim($query);

        if ($needle === '' || ! Schema::hasTable('users')) {
            return [];
        }

        $limit = max(1, min($limit, 10));

        return User::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($needle): void {
                $builder->where('name', 'like', '%'.$needle.'%')
                    ->orWhere('email', 'like', '%'.$needle.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'user_id' => $user->id,
                'name' => (string) $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
