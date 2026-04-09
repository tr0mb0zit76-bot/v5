<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with('role:id,name,display_name')
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role === null ? null : [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                    ],
                    'is_active' => $user->is_active,
                    'has_signing_authority' => (bool) $user->has_signing_authority,
                    'created_at' => optional($user->created_at)?->toIso8601String(),
                ])
                ->values(),
            'roles' => Role::query()
                ->orderBy('display_name')
                ->orderBy('name')
                ->get(['id', 'name', 'display_name'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'default_has_signing_authority' => (bool) ($role->has_signing_authority ?? false),
                ])
                ->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->validated());

        return to_route('settings.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($request->user()?->is($user) && ! $request->boolean('is_active'), 422, 'Вы не можете деактивировать свою учетную запись.');

        $validated = $request->validated();

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        $user->update($validated);

        return to_route('settings.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_if($request->user()?->is($user), 422, 'Вы не можете удалить свою учетную запись.');

        $user->delete();

        return to_route('settings.users.index');
    }
}
