<?php

declare(strict_types=1);

namespace App\Services\LoadBoard;

use App\Models\Contractor;
use App\Models\LoadBoardPost;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoadBoardCarrierPoolCandidateService
{
    public function __construct(
        private readonly LoadBoardCarrierPoolService $carrierPool,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{candidate: array<string, mixed>, pool: array<string, mixed>}
     */
    public function add(LoadBoardPost $post, array $payload, User $user): array
    {
        if (in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            throw ValidationException::withMessages([
                'carrier_id' => 'Нельзя добавить кандидата в закрытый кейс.',
            ]);
        }

        $candidate = $this->normalizeCandidate($payload, $user);

        if ($this->carrierPool->hasEntryForCandidate($post, $candidate)) {
            throw ValidationException::withMessages([
                'carrier_id' => 'Такой перевозчик с этим источником уже есть в пуле или в офферах.',
            ]);
        }

        $metadata = is_array($post->metadata) ? $post->metadata : [];
        $candidates = is_array($metadata['carrier_pool_candidates'] ?? null)
            ? $metadata['carrier_pool_candidates']
            : [];

        $candidates[] = $candidate;
        $metadata['carrier_pool_candidates'] = $candidates;

        $post->update(['metadata' => $metadata]);
        $post->refresh();

        return [
            'candidate' => $candidate,
            'pool' => $this->carrierPool->forPost($post),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(LoadBoardPost $post, string $candidateId): array
    {
        $metadata = is_array($post->metadata) ? $post->metadata : [];
        $candidates = is_array($metadata['carrier_pool_candidates'] ?? null)
            ? $metadata['carrier_pool_candidates']
            : [];

        $next = array_values(array_filter(
            $candidates,
            fn (mixed $row): bool => ! is_array($row) || (string) ($row['id'] ?? '') !== $candidateId,
        ));

        if (count($next) === count($candidates)) {
            throw ValidationException::withMessages([
                'candidate' => 'Кандидат не найден.',
            ]);
        }

        $metadata['carrier_pool_candidates'] = $next;
        $post->update(['metadata' => $metadata]);
        $post->refresh();

        return $this->carrierPool->forPost($post);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCandidate(array $payload, User $user): array
    {
        $carrierId = isset($payload['carrier_id']) && (int) $payload['carrier_id'] > 0
            ? (int) $payload['carrier_id']
            : null;

        $carrierName = trim((string) ($payload['carrier_name'] ?? ''));
        if ($carrierName === '' && $carrierId !== null) {
            $carrierName = (string) (Contractor::query()->whereKey($carrierId)->value('name') ?? '');
        }

        $carrierRate = array_key_exists('carrier_rate', $payload) && $payload['carrier_rate'] !== null && $payload['carrier_rate'] !== ''
            ? round((float) $payload['carrier_rate'], 2)
            : null;

        return [
            'id' => (string) Str::uuid(),
            'carrier_id' => $carrierId,
            'carrier_name' => $carrierName !== '' ? $carrierName : null,
            'source' => (string) $payload['source'],
            'carrier_rate' => $carrierRate,
            'carrier_rate_currency' => strtoupper((string) ($payload['carrier_rate_currency'] ?? 'RUB')),
            'carrier_contact' => $this->nullIfEmpty($payload['carrier_contact'] ?? null),
            'conditions' => $this->nullIfEmpty($payload['conditions'] ?? null),
            'comment' => $this->nullIfEmpty($payload['comment'] ?? null),
            'added_at' => now()->toIso8601String(),
            'added_by' => $user->id,
        ];
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
