<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AgentWriteConfirmationService
{
    private const int TTL_SECONDS = 900;

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $preview
     */
    public function issue(User $user, string $toolName, array $args, array $preview): string
    {
        $token = Str::lower(Str::random(32));

        Cache::put(
            $this->cacheKey($user, $token),
            [
                'tool' => $toolName,
                'args_hash' => $this->hashArgs($args),
                'preview' => $preview,
            ],
            self::TTL_SECONDS,
        );

        return $token;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function consume(User $user, string $toolName, string $confirmToken, array $args): void
    {
        $token = trim($confirmToken);

        if ($token === '') {
            throw ValidationException::withMessages([
                'confirm_token' => 'Укажите confirm_token из dry_run.',
            ]);
        }

        $payload = Cache::pull($this->cacheKey($user, $token));

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'confirm_token' => 'Токен подтверждения недействителен или истёк (15 мин). Повторите dry_run.',
            ]);
        }

        if (($payload['tool'] ?? '') !== $toolName) {
            throw ValidationException::withMessages([
                'confirm_token' => 'Токен выдан для другой операции.',
            ]);
        }

        if (($payload['args_hash'] ?? '') !== $this->hashArgs($args)) {
            throw ValidationException::withMessages([
                'confirm_token' => 'Параметры изменились после dry_run. Повторите dry_run.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function hashArgs(array $args): string
    {
        ksort($args);

        return hash('sha256', json_encode($args, JSON_THROW_ON_ERROR));
    }

    private function cacheKey(User $user, string $token): string
    {
        return 'agent_write_confirm:'.$user->id.':'.$token;
    }
}
