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
            return str_contains($request->url(), 'fcm.googleapis.com')
                && ($request->data()['message']['token'] ?? null) === 'device-token-abc'
                && ($request->data()['message']['data']['kind'] ?? null) === 'order_document_approval';
        });
    }
}
