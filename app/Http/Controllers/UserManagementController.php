<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\UserDepartmentSync;
use App\Support\UserSigningOwnCompanySync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $ownCompanies = $this->ownCompaniesPayload();

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with([
                    'role:id,name,display_name',
                    'roles:id,name,display_name',
                    'signingOwnCompanies:id,name',
                    'departments:id,name',
                ])
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->serializeUser($user))
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
            'ownCompanies' => $ownCompanies,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $signingOwnCompanyIds = $validated['signing_own_company_ids'] ?? [];
        $roleIds = $validated['role_ids'] ?? [];
        $mailPassword = $validated['mail_password'] ?? null;
        $primaryDepartmentId = isset($validated['primary_department_id']) ? (int) $validated['primary_department_id'] : null;
        $approvalDepartmentIds = $validated['approval_department_ids'] ?? [];
        unset(
            $validated['signing_own_company_ids'],
            $validated['role_ids'],
            $validated['mail_password'],
            $validated['primary_department_id'],
            $validated['approval_department_ids'],
        );

        $user = User::query()->create($validated);
        $user->applyMailImapPassword(is_string($mailPassword) ? $mailPassword : null);

        if ($user->isDirty('mail_imap_secret')) {
            $user->save();
        }

        RoleAccess::syncUserRoles($user, is_array($roleIds) ? $roleIds : []);

        UserSigningOwnCompanySync::sync(
            $user,
            (bool) ($validated['has_signing_authority'] ?? false),
            is_array($signingOwnCompanyIds) ? $signingOwnCompanyIds : [],
        );

        UserDepartmentSync::sync(
            $user,
            $primaryDepartmentId > 0 ? $primaryDepartmentId : null,
            is_array($approvalDepartmentIds) ? $approvalDepartmentIds : [],
        );

        return to_route('settings.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($request->user()?->is($user) && ! $request->boolean('is_active'), 422, 'Вы не можете деактивировать свою учетную запись.');

        $validated = $request->validated();
        $signingOwnCompanyIds = $validated['signing_own_company_ids'] ?? [];
        $roleIds = $validated['role_ids'] ?? null;
        $mailPassword = $validated['mail_password'] ?? null;
        $primaryDepartmentId = array_key_exists('primary_department_id', $validated)
            ? (int) $validated['primary_department_id']
            : null;
        $approvalDepartmentIds = $validated['approval_department_ids'] ?? null;
        unset(
            $validated['signing_own_company_ids'],
            $validated['role_ids'],
            $validated['mail_password'],
            $validated['primary_department_id'],
            $validated['approval_department_ids'],
        );

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->applyMailImapPassword(is_string($mailPassword) ? $mailPassword : null);

        if ($user->isDirty('mail_imap_secret')) {
            $user->save();
        }

        if (is_array($roleIds)) {
            RoleAccess::syncUserRoles($user, $roleIds);
        }

        UserSigningOwnCompanySync::sync(
            $user,
            (bool) ($validated['has_signing_authority'] ?? false),
            is_array($signingOwnCompanyIds) ? $signingOwnCompanyIds : [],
        );

        if ($primaryDepartmentId !== null || is_array($approvalDepartmentIds)) {
            UserDepartmentSync::sync(
                $user,
                $primaryDepartmentId !== null && $primaryDepartmentId > 0 ? $primaryDepartmentId : null,
                is_array($approvalDepartmentIds) ? $approvalDepartmentIds : [],
            );
        }

        return to_route('settings.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_if($request->user()?->is($user), 422, 'Вы не можете удалить свою учетную запись.');

        $user->delete();

        return to_route('settings.users.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $signingOwnCompanyIds = $user->signingOwnCompanyIds();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'role_ids' => RoleAccess::userRoleIds($user),
            'has_password' => filled($user->getRawOriginal('password')),
            'has_mail_imap_password' => $user->hasMailImapCredential(),
            'mail_sync_enabled' => (bool) ($user->mail_sync_enabled ?? true),
            'mail_last_sync_at' => $user->mail_last_sync_at?->toIso8601String(),
            'mail_last_sync_error' => $user->mail_last_sync_error,
            'role' => $user->role === null ? null : [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'display_name' => $user->role->display_name,
            ],
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ])->values()->all()
                : [],
            'roles_label' => $this->rolesLabel($user),
            'is_active' => $user->is_active,
            'has_signing_authority' => (bool) $user->has_signing_authority,
            'belongs_to_management' => (bool) $user->belongs_to_management,
            'can_management_accounting' => (bool) $user->can_management_accounting,
            'sees_company_dashboard' => (bool) ($user->sees_company_dashboard ?? false),
            'signing_own_company_ids' => $signingOwnCompanyIds,
            'signing_own_companies_unrestricted' => $user->signingOwnCompaniesUnrestricted(),
            'signing_own_companies_label' => $this->signingOwnCompaniesLabel($user, $signingOwnCompanyIds),
            'primary_department_id' => $user->primaryDepartmentId(),
            'approval_department_ids' => $user->approvalDepartmentIds(),
            'departments_label' => $this->departmentsLabel($user),
            'created_at' => optional($user->created_at)?->toIso8601String(),
        ];
    }

    private function departmentsLabel(User $user): string
    {
        if (! $user->relationLoaded('departments') || $user->departments->isEmpty()) {
            return '—';
        }

        $primaryId = $user->primaryDepartmentId();
        $names = $user->departments
            ->sortBy(fn (Department $department): int => $primaryId !== null && (int) $department->id === $primaryId ? 0 : 1)
            ->map(function (Department $department) use ($primaryId): string {
                $name = (string) $department->name;
                $flags = [];

                if ($primaryId !== null && (int) $department->id === $primaryId) {
                    $flags[] = 'основное';
                }

                if ((bool) $department->pivot->receives_approvals) {
                    $flags[] = 'согласования';
                }

                if ($flags === []) {
                    return $name;
                }

                return sprintf('%s (%s)', $name, implode(', ', $flags));
            })
            ->values();

        return $names->implode('; ');
    }

    /**
     * @param  list<int>  $signingOwnCompanyIds
     */
    private function signingOwnCompaniesLabel(User $user, array $signingOwnCompanyIds): string
    {
        if (! $user->has_signing_authority) {
            return 'Нет';
        }

        if ($signingOwnCompanyIds === []) {
            return 'Все компании';
        }

        if ($user->relationLoaded('signingOwnCompanies')) {
            $names = $user->signingOwnCompanies->pluck('name')->filter()->values();

            return $names->isNotEmpty() ? $names->implode(', ') : 'Выбранные компании';
        }

        return 'Выбранные компании';
    }

    private function rolesLabel(User $user): string
    {
        $roles = $user->relationLoaded('roles') && $user->roles->isNotEmpty()
            ? $user->roles
            : RoleAccess::assignedRoles($user);

        if ($roles->isEmpty()) {
            return 'Без роли';
        }

        return $roles
            ->map(fn (Role $role): string => (string) ($role->display_name ?: $role->name))
            ->filter()
            ->implode(', ');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ownCompaniesPayload(): array
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'is_own_company')) {
            return [];
        }

        return Contractor::query()
            ->where('is_own_company', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
            ])
            ->values()
            ->all();
    }
}
