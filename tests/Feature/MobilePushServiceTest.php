<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMobileDevice;
use App\Notifications\CabinetInAppNotification;
use App\Services\Mobile\MobilePushService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobilePushServiceTest extends TestCase
{
    public function test_cabinet_notification_uses_database_channel_only(): void
    {
        $notification = new CabinetInAppNotification(
            'order_document_approval',
            'Согласование',
            'Текст',
            '/orders/1',
            ['order_id' => 1],
        );

        $this->assertSame(['database'], $notification->via(User::factory()->make()));
    }

    public function test_mobile_push_skips_non_whitelisted_kind(): void
    {
        config(['fcm.enabled' => true]);

        Http::fake();

        $user = User::factory()->create();

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => '11111111-1111-4111-8111-111111111111',
            'pin_hash' => bcrypt('1234'),
            'fcm_token' => 'token-should-not-send',
        ]);

        app(MobilePushService::class)->notifyCabinetNotification(
            $user,
            new CabinetInAppNotification(
                'task_assigned',
                'Задача',
                'Текст',
                '/tasks',
                ['task_id' => 1],
            ),
        );

        Http::assertNothingSent();
    }

    public function test_mobile_push_sends_for_whitelisted_kind_when_enabled(): void
    {
        config([
            'fcm.enabled' => true,
            'fcm.project_id' => 'test-project',
            'fcm.access_token_override' => 'fake-access-token',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
        ]);

        $user = User::factory()->create();

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => '22222222-2222-4222-8222-222222222222',
            'pin_hash' => bcrypt('1234'),
            'fcm_token' => 'device-token-abc',
        ]);

        app(MobilePushService::class)->notifyCabinetNotification(
            $user,
            new CabinetInAppNotification(
                'order_document_approval',
                'Согласование заявки',
                'Нужно подписать',
                '/orders/10',
                ['order_id' => 10],
            ),
        );

        Http::assertSent(function ($request): bool {
            $message = $request->data()['message'] ?? [];

            return str_contains($request->url(), 'fcm.googleapis.com')
                && ($message['token'] ?? null) === 'device-token-abc'
                && ($message['data']['kind'] ?? null) === 'order_document_approval'
                && ($message['data']['title'] ?? null) === 'Согласование заявки'
                && ($message['data']['body'] ?? null) === 'Нужно подписать'
                && ($message['data']['push_action_label'] ?? null) === 'Открыть'
                && ! array_key_exists('notification', $message);
        });
    }

    public function test_mobile_push_chat_message_uses_read_action_label(): void
    {
        config([
            'fcm.enabled' => true,
            'fcm.project_id' => 'test-project',
            'fcm.access_token_override' => 'fake-access-token',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
        ]);

        $user = User::factory()->create();

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => '33333333-3333-4333-8333-333333333333',
            'pin_hash' => bcrypt('1234'),
            'fcm_token' => 'chat-device-token',
        ]);

        app(MobilePushService::class)->notifyUser(
            $user,
            'chat_message',
            'Менеджер',
            'Новое сообщение',
            ['conversation_id' => 42],
        );

        Http::assertSent(function ($request): bool {
            $message = $request->data()['message'] ?? [];

            return ($message['data']['push_action_label'] ?? null) === 'Прочитать'
                && ($message['data']['conversation_id'] ?? null) === '42';
        });
    }
}
