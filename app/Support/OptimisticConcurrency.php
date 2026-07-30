<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Защита от редкого одновременного редактирования одной строки двумя людьми.
 */
final class OptimisticConcurrency
{
    public static function assertUnchanged(Model $model, mixed $expectedUpdatedAt): void
    {
        if ($expectedUpdatedAt === null || $expectedUpdatedAt === '') {
            return;
        }

        if (! $model->updated_at instanceof Carbon) {
            return;
        }

        $expected = self::normalizeTimestamp($expectedUpdatedAt);
        $current = self::normalizeTimestamp($model->updated_at);

        if ($expected === null || $current === null) {
            return;
        }

        if ($expected === $current) {
            return;
        }

        $message = 'Запись изменена другим пользователем. Обновите данные и повторите.';
        $request = request();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
                'code' => 'concurrency_conflict',
                'updated_at' => $model->updated_at->toIso8601String(),
            ], 409));
        }

        throw ValidationException::withMessages([
            'concurrency' => $message,
            'expected_updated_at' => $message,
        ]);
    }

    private static function normalizeTimestamp(mixed $value): ?string
    {
        try {
            if ($value instanceof Carbon) {
                return $value->clone()->utc()->format('Y-m-d\TH:i:s');
            }

            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }

            return Carbon::parse((string) $value)->utc()->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
