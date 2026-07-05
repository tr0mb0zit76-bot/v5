<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Models\UserMobileDevice;
use App\Notifications\CabinetInAppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MobilePushService
{
    public function notifyCabinetNotification(User $user, CabinetInAppNotification $notification): void
    {
        if (! $this->shouldPushKind($notification->kind)) {
            return;
        }

        $this->notifyUser(
            $user,
            $notification->kind,
            $notification->title,
            $notification->body,
            $this->buildDataPayload($notification),
        );
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $users
     */
    public function notifyUsers(iterable $users, string $kind, string $title, string $body, array $data = []): void
    {
        if (! $this->shouldPushKind($kind)) {
            return;
        }

        foreach ($users as $user) {
            if ($user instanceof User) {
                $this->notifyUser($user, $kind, $title, $body, $data);
            }
        }
    }

    public function notifyUser(User $user, string $kind, string $title, string $body, array $data = []): void
    {
        if (! config('fcm.enabled') || ! Schema::hasTable('user_mobile_devices')) {
            return;
        }

        if (! $this->shouldPushKind($kind)) {
            return;
        }

        $tokens = $this->tokensForUser($user);

        foreach ($tokens as $token) {
            $this->sendToToken($token, [
                'title' => $title,
                'body' => $body,
                'channel_id' => $this->channelForKind($kind),
                'data' => array_merge(
                    ['kind' => $kind],
                    $this->stringifyData($data),
                ),
            ]);
        }
    }

    /**
     * @param  array{title: string, body: string, channel_id: string, data: array<string, string>}  $payload
     */
    public function sendToToken(string $token, array $payload): void
    {
        if (! config('fcm.enabled')) {
            Log::debug('FCM disabled, skip push', ['token_prefix' => substr($token, 0, 12)]);

            return;
        }

        $accessToken = $this->resolveAccessToken();
        $projectId = config('fcm.project_id');

        if ($accessToken === null || ! is_string($projectId) || $projectId === '') {
            Log::warning('FCM misconfigured: missing credentials or project id');

            return;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'data' => array_merge(
                        $payload['data'],
                        [
                            'title' => $payload['title'],
                            'body' => $payload['body'],
                            'channel_id' => $payload['channel_id'],
                            'push_action_label' => $this->actionLabelForKind($payload['data']['kind'] ?? ''),
                        ],
                    ),
                    'android' => [
                        'priority' => 'HIGH',
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function shouldPushKind(string $kind): bool
    {
        /** @var list<string> $kinds */
        $kinds = config('fcm.push_kinds', []);

        return in_array($kind, $kinds, true);
    }

    private function channelForKind(string $kind): string
    {
        /** @var array<string, string> $channels */
        $channels = config('fcm.android_channels', []);

        return $channels[$kind] ?? (string) config('fcm.default_android_channel_id', 'crm_chat_messages');
    }

    private function actionLabelForKind(string $kind): string
    {
        return match ($kind) {
            'chat_message' => 'Прочитать',
            default => 'Открыть',
        };
    }

    /**
     * @return list<string>
     */
    private function tokensForUser(User $user): array
    {
        return UserMobileDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter(fn (?string $token): bool => is_string($token) && $token !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function buildDataPayload(CabinetInAppNotification $notification): array
    {
        $data = [
            'action_url' => $notification->actionUrl,
        ];

        foreach ($notification->payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[(string) $key] = $value === null ? '' : (string) $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $result;
    }

    private function resolveAccessToken(): ?string
    {
        $override = config('fcm.access_token_override');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $credentials = config('fcm.credentials');

        if (! is_string($credentials) || $credentials === '') {
            return null;
        }

        if (is_file($credentials)) {
            $credentials = (string) file_get_contents($credentials);
        }

        $json = json_decode($credentials, true);

        if (! is_array($json) || ! isset($json['client_email'], $json['private_key'], $json['token_uri'])) {
            return null;
        }

        $now = time();
        $jwtHeader = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $jwtClaim = rtrim(strtr(base64_encode(json_encode([
            'iss' => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $json['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ])), '+/', '-_'), '=');

        $unsigned = $jwtHeader.'.'.$jwtClaim;
        $signature = '';
        openssl_sign($unsigned, $signature, $json['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $response = Http::asForm()->post($json['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            Log::warning('FCM token exchange failed', ['body' => $response->body()]);

            return null;
        }

        return $response->json('access_token');
    }
}
