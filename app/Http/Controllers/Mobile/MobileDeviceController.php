<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CheckMobileDeviceRequest;
use App\Http\Requests\Mobile\RegisterMobileDeviceRequest;
use App\Http\Requests\Mobile\UnlockMobileDeviceRequest;
use App\Http\Requests\Mobile\UpdateMobileFcmTokenRequest;
use App\Services\Mobile\MobileDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MobileDeviceController extends Controller
{
    public function __construct(
        private MobileDeviceService $mobileDeviceService,
    ) {}

    public function check(CheckMobileDeviceRequest $request): JsonResponse
    {
        return response()->json(
            $this->mobileDeviceService->publicDeviceHint($request->string('device_key')->toString()),
        );
    }

    public function createPinSetup(Request $request): Response|RedirectResponse
    {
        $deviceKey = $request->string('device_key')->toString();

        if ($deviceKey === '') {
            return redirect()->route('mobile.messenger.app');
        }

        if ($this->mobileDeviceService->deviceNeedsPinSetup($request->user(), $deviceKey)) {
            return Inertia::render('Mobile/PinSetup', [
                'deviceKey' => $deviceKey,
            ]);
        }

        return redirect()->route('mobile.messenger.app');
    }

    public function storePinSetup(RegisterMobileDeviceRequest $request): RedirectResponse
    {
        $this->mobileDeviceService->registerPin(
            $request->user(),
            $request->string('device_key')->toString(),
            $request->string('pin')->toString(),
            $request->string('device_name')->toString() ?: null,
        );

        return redirect()->route('mobile.messenger.app');
    }

    public function unlock(UnlockMobileDeviceRequest $request): RedirectResponse
    {
        $deviceKey = $request->string('device_key')->toString();
        $user = $this->mobileDeviceService->unlockWithPin(
            $deviceKey,
            $request->string('pin')->toString(),
            (string) $request->ip(),
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('mobile.messenger.app', absolute: false));
    }

    public function updateFcmToken(UpdateMobileFcmTokenRequest $request): JsonResponse
    {
        $this->mobileDeviceService->updateFcmToken(
            $request->user(),
            $request->string('device_key')->toString(),
            $request->string('fcm_token')->toString() ?: null,
        );

        return response()->json(['ok' => true]);
    }
}
