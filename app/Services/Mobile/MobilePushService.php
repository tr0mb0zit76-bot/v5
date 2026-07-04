<?php

namespace App\Services\Mobile;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserMobileDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MobilePushService
{
    public function notifyChatMessage(ChatMessage $message, User $author): void
    {
        if (! config('fcm.enabled') || ! Schema::hasTable('user_mobile_devices')) {
            return;
        }

        $message->loadMissing(['conversation.participants', 'recipient']);
        $conversation = $message->conversation;

        if ($conversation === null) {
            return;
        }

        $recipients = $conversation->participants
            ->filter(fn (User $participant): bool => (int) $participant->id !== (int) $author->id);

        if ($message->recipient_user_id !== null) {
            $recipients = $recipients
                ->filter(fn (User $participant): bool => (int) $participant->id === (int) $message->recipient_user_id);
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $conversationTitle = $conversation->type === 'group'
            ? ($conversation->title ?: 'Групповой чат')
            : $author->name;

        $body = mb_strimwidth((string) $message->body, 0, 160, '…');

        foreach ($recipients as $recipient) {
            $tokens = UserMobileDevice::query()
                ->where('user_id', $recipient->id)
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->filter(fn (?string $token): bool => is_string($token) && $token !== '')
                ->unique()
                ->values()
                ->all();

            foreach ($tokens as $token) {
                $this->sendToToken($token, [
                    'title' => $conversationTitle,
                    'body' => $body,
                    'data' => [
                        'kind' => 'chat_message',
                        'conversation_id' => (string) $conversation->id,
                        'message_id' => (string) $message->id,
                    ],
                ]);
            }
        }
    }

    /**
     * @param  array{title: string, body: string, data: array<string, string>}  $payload
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
                    'notification' => [
                        'title' => $payload['title'],
                        'body' => $payload['body'],
                    ],
                    'data' => $payload['data'],
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => config('fcm.default_android_channel_id'),
                        ],
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

    private function resolveAccessToken(): ?string
    {
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
