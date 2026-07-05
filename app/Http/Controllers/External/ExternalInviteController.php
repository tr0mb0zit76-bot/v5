<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\ActivateExternalInviteRequest;
use App\Models\User;
use App\Services\ExternalUsers\ExternalUserInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExternalInviteController extends Controller
{
    public function __construct(
        private readonly ExternalUserInviteService $inviteService,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invite = $this->inviteService->resolveByToken($token);

        if ($invite === null || ! $invite->isOpen()) {
            return Inertia::render('External/InviteExpired');
        }

        $user = $invite->user;

        if ($user instanceof User && filled($user->getRawOriginal('password')) && ! $request->boolean('renew')) {
            return redirect()->route('mobile.login');
        }

        return Inertia::render('External/InviteActivate', [
            'token' => $token,
            'contact_name' => $invite->contact?->full_name,
            'email' => $invite->contact?->email,
            'contractor_name' => $invite->contractor?->name,
            'external_party_label' => $invite->external_party === 'carrier' ? 'Перевозчик' : 'Заказчик',
            'traklo_apk_url' => config('external_users.apk_url', '/downloads/traklo.apk'),
            'expires_at' => $invite->expires_at?->toIso8601String(),
        ]);
    }

    public function store(ActivateExternalInviteRequest $request, string $token): RedirectResponse
    {
        $invite = $this->inviteService->resolveByToken($token);

        if ($invite === null || ! $invite->isOpen()) {
            abort(HttpResponse::HTTP_GONE, 'Ссылка недействительна или истекла.');
        }

        $user = $invite->user;

        if (! $user instanceof User) {
            abort(HttpResponse::HTTP_GONE, 'Пользователь не найден.');
        }

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'is_active' => true,
        ])->save();

        $invite->forceFill(['consumed_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('mobile.messenger.app');
    }
}
