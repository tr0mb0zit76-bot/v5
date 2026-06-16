<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateMobileBottomNavRequest;
use App\Http\Requests\UpdateSidebarFavoritesRequest;
use App\Http\Requests\UpdateUiPreferencesRequest;
use App\Support\CrmAppearance;
use App\Support\MobileNavResolver;
use App\Support\SidebarMenuFavoritesResolver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Порядок и состав кнопок нижней панели в мобильном PWA (личные настройки пользователя).
     */
    public function updateMobileBottomNav(UpdateMobileBottomNavRequest $request): RedirectResponse
    {
        if (! Schema::hasColumn('users', 'mobile_nav_keys')) {
            abort(404);
        }

        $user = $request->user();
        $keys = MobileNavResolver::sanitizeUserSelection(
            $user,
            $request->validated('mobile_nav_keys'),
        );
        $user->mobile_nav_keys = $keys === [] ? null : $keys;
        $user->save();

        return Redirect::back(303);
    }

    /**
     * Закреплённые пункты бокового меню (до 5 быстрых ссылок).
     */
    public function updateSidebarFavorites(UpdateSidebarFavoritesRequest $request): RedirectResponse
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            abort(404);
        }

        $user = $request->user();
        $keys = SidebarMenuFavoritesResolver::sanitizeUserSelection(
            $user,
            $request->validated('sidebar_favorite_keys'),
        );

        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        if ($keys === []) {
            unset($preferences['sidebar_favorite_keys']);
        } else {
            $preferences['sidebar_favorite_keys'] = $keys;
        }

        $user->ui_preferences = $preferences;
        $user->save();

        return Redirect::back(303);
    }

    /**
     * Личные настройки интерфейса (плотность AG Grid и др.).
     */
    public function updateUiPreferences(UpdateUiPreferencesRequest $request): RedirectResponse
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            abort(404);
        }

        $user = $request->user();
        $user->ui_preferences = CrmAppearance::mergeValidated(
            $request->validated(),
            is_array($user->ui_preferences) ? $user->ui_preferences : null,
        );
        $user->save();

        return Redirect::back();
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
