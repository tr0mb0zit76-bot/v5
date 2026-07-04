<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMobileDevice;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileDeviceAuthTest extends TestCase
{
    public function test_device_check_returns_registration_hint(): void
    {
        $user = User::factory()->create(['name' => 'Иван Менеджер']);
        $deviceKey = '11111111-1111-4111-8111-111111111111';

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => $deviceKey,
            'pin_hash' => Hash::make('1234'),
            'device_name' => 'Pixel Test',
        ]);

        $this->postJson(route('mobile.device.check'), [
            'device_key' => $deviceKey,
        ])->assertOk()
            ->assertJson([
                'registered' => true,
                'user_name' => 'Иван Менеджер',
                'device_name' => 'Pixel Test',
            ]);
    }

    public function test_pin_unlock_restores_session(): void
    {
        $user = User::factory()->create();
        $deviceKey = '22222222-2222-4222-8222-222222222222';

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => $deviceKey,
            'pin_hash' => Hash::make('4321'),
        ]);

        $this->post(route('mobile.pin-unlock'), [
            'device_key' => $deviceKey,
            'pin' => '4321',
        ])->assertRedirect(route('mobile.messenger.app'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $user = User::factory()->create();
        $deviceKey = '33333333-3333-4333-8333-333333333333';

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => $deviceKey,
            'pin_hash' => Hash::make('1234'),
        ]);

        $this->from(route('mobile.login'))
            ->post(route('mobile.pin-unlock'), [
                'device_key' => $deviceKey,
                'pin' => '9999',
            ])
            ->assertRedirect(route('mobile.login'))
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_password_login_redirects_to_pin_setup_for_new_device(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);
        $deviceKey = '44444444-4444-4444-8444-444444444444';

        $this->post(route('mobile.login.store'), [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_key' => $deviceKey,
        ])->assertRedirect(route('mobile.pin-setup', ['device_key' => $deviceKey]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_pin_setup_registers_device(): void
    {
        $user = User::factory()->create();
        $deviceKey = '55555555-5555-4555-8555-555555555555';

        $this->actingAs($user)
            ->post(route('mobile.pin-setup.store'), [
                'device_key' => $deviceKey,
                'device_name' => 'Test Phone',
                'pin' => '2468',
                'pin_confirmation' => '2468',
            ])
            ->assertRedirect(route('mobile.messenger.app'));

        $this->assertDatabaseHas('user_mobile_devices', [
            'user_id' => $user->id,
            'device_key' => $deviceKey,
            'device_name' => 'Test Phone',
        ]);
    }

    public function test_authenticated_user_can_store_fcm_token(): void
    {
        $user = User::factory()->create();
        $deviceKey = '66666666-6666-4666-8666-666666666666';

        UserMobileDevice::query()->create([
            'user_id' => $user->id,
            'device_key' => $deviceKey,
            'pin_hash' => Hash::make('1234'),
        ]);

        $this->actingAs($user)
            ->postJson(route('mobile.device.fcm-token'), [
                'device_key' => $deviceKey,
                'fcm_token' => 'fcm-test-token-abc',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('user_mobile_devices', [
            'device_key' => $deviceKey,
            'fcm_token' => 'fcm-test-token-abc',
        ]);
    }
}
