<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Models\UserMobileDevice;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileDeviceService
{
    public const PIN_MIN_LENGTH = 4;

    public const PIN_MAX_LENGTH = 6;

    public const MAX_PIN_ATTEMPTS = 5;

    /**
     * @return array{registered: bool, user_name: string|null, device_name: string|null}
     */
    public function publicDeviceHint(string $deviceKey): array
    {
        $device = UserMobileDevice::query()
            ->with('user:id,name')
            ->where('device_key', $deviceKey)
            ->first();

        if ($device === null || $device->user === null) {
            return [
                'registered' => false,
                'user_name' => null,
                'device_name' => null,
            ];
        }

        return [
            'registered' => true,
            'user_name' => $device->user->name,
            'device_name' => $device->device_name,
        ];
    }

    public function deviceNeedsPinSetup(User $user, string $deviceKey): bool
    {
        return ! UserMobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_key', $deviceKey)
            ->exists();
    }

    public function registerPin(User $user, string $deviceKey, string $pin, ?string $deviceName = null): UserMobileDevice
    {
        $device = UserMobileDevice::query()->firstOrNew([
            'device_key' => $deviceKey,
        ]);

        $device->fill([
            'user_id' => $user->id,
            'pin_hash' => Hash::make($pin),
            'device_name' => $deviceName !== null && $deviceName !== '' ? $deviceName : $device->device_name,
            'failed_pin_attempts' => 0,
            'pin_locked_until' => null,
            'last_used_at' => now(),
        ]);
        $device->save();

        return $device;
    }

    public function unlockWithPin(string $deviceKey, string $pin, string $ip): User
    {
        $this->ensurePinUnlockIsNotRateLimited($deviceKey, $ip);

        $device = UserMobileDevice::query()
            ->with('user')
            ->where('device_key', $deviceKey)
            ->first();

        if ($device === null || $device->user === null) {
            $this->hitPinUnlockRateLimit($deviceKey, $ip);

            throw ValidationException::withMessages([
                'pin' => 'Устройство не зарегистрировано. Войдите email и паролем.',
            ]);
        }

        if ($device->pin_locked_until !== null && $device->pin_locked_until->isFuture()) {
            $seconds = now()->diffInSeconds($device->pin_locked_until);

            throw ValidationException::withMessages([
                'pin' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Hash::check($pin, $device->pin_hash)) {
            $device->failed_pin_attempts = (int) $device->failed_pin_attempts + 1;

            if ($device->failed_pin_attempts >= self::MAX_PIN_ATTEMPTS) {
                $device->pin_locked_until = now()->addMinutes(15);
                $device->failed_pin_attempts = 0;
            }

            $device->save();
            $this->hitPinUnlockRateLimit($deviceKey, $ip);

            throw ValidationException::withMessages([
                'pin' => 'Неверный PIN-код.',
            ]);
        }

        $device->forceFill([
            'failed_pin_attempts' => 0,
            'pin_locked_until' => null,
            'last_used_at' => now(),
        ])->save();

        RateLimiter::clear($this->pinUnlockThrottleKey($deviceKey, $ip));

        return $device->user;
    }

    public function updateFcmToken(User $user, string $deviceKey, ?string $token): void
    {
        UserMobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_key', $deviceKey)
            ->update([
                'fcm_token' => $token !== null && $token !== '' ? $token : null,
                'last_used_at' => now(),
            ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensurePinUnlockIsNotRateLimited(string $deviceKey, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($this->pinUnlockThrottleKey($deviceKey, $ip), self::MAX_PIN_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->pinUnlockThrottleKey($deviceKey, $ip));

        throw ValidationException::withMessages([
            'pin' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    public function hitPinUnlockRateLimit(string $deviceKey, string $ip): void
    {
        RateLimiter::hit($this->pinUnlockThrottleKey($deviceKey, $ip), 900);
    }

    public function pinUnlockThrottleKey(string $deviceKey, string $ip): string
    {
        return Str::transliterate('mobile-pin|'.$deviceKey.'|'.$ip);
    }
}
