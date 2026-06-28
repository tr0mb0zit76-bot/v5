<?php

namespace App\Services;

use App\Models\GridView;
use App\Models\Role;
use App\Models\User;
use App\Support\GridViewCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GridViewService
{
    /**
     * @return Collection<int, GridView>
     */
    public function listForGrid(User $user, string $gridKey): Collection
    {
        $this->assertGridAccess($user, $gridKey);

        return $this->visibleQuery($user, $gridKey)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string, grid_key: string, grid_label: string, url: string}>
     */
    public function pinnedForSidebar(User $user): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->visibleQuery($user)
            ->where('is_pinned_sidebar', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (GridView $view): bool => GridViewCatalog::userCanAccessGrid($user, $view->grid_key))
            ->map(fn (GridView $view): array => [
                'id' => $view->id,
                'name' => $view->name,
                'grid_key' => $view->grid_key,
                'grid_label' => GridViewCatalog::labelFor($view->grid_key),
                'url' => GridViewCatalog::urlForView($view->grid_key, $view->id) ?? '#',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     grid_key: string,
     *     name: string,
     *     visibility?: string,
     *     shared_with?: array<string, mixed>|null,
     *     column_state?: array<int, mixed>|null,
     *     filter_state?: array<string, mixed>|null,
     *     sort_state?: array<int, mixed>|null,
     *     quick_search?: string|null,
     *     is_pinned_sidebar?: bool,
     * }  $payload
     */
    public function create(User $user, array $payload): GridView
    {
        $gridKey = (string) $payload['grid_key'];
        $this->assertGridAccess($user, $gridKey);
        $visibility = $this->normalizeVisibility($user, (string) ($payload['visibility'] ?? 'private'));

        $sortOrder = ((int) GridView::query()
            ->where('owner_user_id', $user->id)
            ->where('grid_key', $gridKey)
            ->max('sort_order')) + 10;

        return GridView::query()->create([
            'grid_key' => $gridKey,
            'name' => trim((string) $payload['name']),
            'owner_user_id' => $user->id,
            'visibility' => $visibility,
            'shared_with' => $this->normalizeSharedWith($payload['shared_with'] ?? null),
            'column_state' => $payload['column_state'] ?? null,
            'filter_state' => $payload['filter_state'] ?? null,
            'sort_state' => $payload['sort_state'] ?? null,
            'quick_search' => $this->normalizeQuickSearch($payload['quick_search'] ?? null),
            'is_pinned_sidebar' => (bool) ($payload['is_pinned_sidebar'] ?? false),
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $user, GridView $view, array $payload): GridView
    {
        $this->assertCanManage($user, $view);

        if (isset($payload['name'])) {
            $view->name = trim((string) $payload['name']);
        }

        if (isset($payload['visibility'])) {
            $view->visibility = $this->normalizeVisibility($user, (string) $payload['visibility']);
        }

        if (array_key_exists('shared_with', $payload)) {
            $view->shared_with = $this->normalizeSharedWith($payload['shared_with']);
        }

        if (array_key_exists('column_state', $payload)) {
            $view->column_state = $payload['column_state'];
        }

        if (array_key_exists('filter_state', $payload)) {
            $view->filter_state = $payload['filter_state'];
        }

        if (array_key_exists('sort_state', $payload)) {
            $view->sort_state = $payload['sort_state'];
        }

        if (array_key_exists('quick_search', $payload)) {
            $view->quick_search = $this->normalizeQuickSearch($payload['quick_search']);
        }

        if (array_key_exists('is_pinned_sidebar', $payload)) {
            $view->is_pinned_sidebar = (bool) $payload['is_pinned_sidebar'];
        }

        $view->save();

        return $view->fresh();
    }

    public function delete(User $user, GridView $view): void
    {
        $this->assertCanManage($user, $view);
        $view->delete();
    }

    public function userCanApply(User $user, GridView $view): bool
    {
        if (! GridViewCatalog::userCanAccessGrid($user, $view->grid_key)) {
            return false;
        }

        return $this->visibleQuery($user, $view->grid_key)
            ->whereKey($view->id)
            ->exists();
    }

    public function userCanShare(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    /**
     * @return array{
     *     roles: list<array{id: int, label: string}>,
     *     users: list<array{id: int, label: string}>
     * }
     */
    public function shareOptionsFor(User $user): array
    {
        if (! $this->userCanShare($user)) {
            return ['roles' => [], 'users' => []];
        }

        $roles = Role::query()
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name'])
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'label' => (string) ($role->display_name ?: $role->name),
            ])
            ->values()
            ->all();

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $row): array => [
                'id' => (int) $row->id,
                'label' => trim($row->name.' · '.$row->email),
            ])
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'users' => $users,
        ];
    }

    public function assertCanManage(User $user, GridView $view): void
    {
        if ((int) $view->owner_user_id === (int) $user->id || $user->isAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'grid_view' => 'Недостаточно прав для изменения представления.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(GridView $view, User $viewer): array
    {
        return [
            'id' => $view->id,
            'grid_key' => $view->grid_key,
            'name' => $view->name,
            'visibility' => $view->visibility,
            'shared_with' => $view->shared_with ?? [],
            'column_state' => $view->column_state ?? [],
            'filter_state' => $view->filter_state ?? [],
            'sort_state' => $view->sort_state ?? [],
            'quick_search' => $view->quick_search ?? '',
            'is_pinned_sidebar' => (bool) $view->is_pinned_sidebar,
            'sort_order' => (int) $view->sort_order,
            'owner_user_id' => $view->owner_user_id,
            'is_owner' => (int) $view->owner_user_id === (int) $viewer->id,
            'can_manage' => (int) $view->owner_user_id === (int) $viewer->id || $viewer->isAdmin(),
            'url' => GridViewCatalog::urlForView($view->grid_key, $view->id),
        ];
    }

    private function assertGridAccess(User $user, string $gridKey): void
    {
        if (! GridViewCatalog::isValidGridKey($gridKey)) {
            throw ValidationException::withMessages([
                'grid_key' => 'Неизвестный грид.',
            ]);
        }

        if (! GridViewCatalog::userCanAccessGrid($user, $gridKey)) {
            abort(403);
        }
    }

    private function normalizeVisibility(User $user, string $visibility): string
    {
        if (! in_array($visibility, GridViewCatalog::visibilityOptions(), true)) {
            throw ValidationException::withMessages([
                'visibility' => 'Недопустимый тип доступа.',
            ]);
        }

        if ($visibility !== 'private' && ! $this->userCanShare($user)) {
            throw ValidationException::withMessages([
                'visibility' => 'Делиться представлениями могут только администратор и руководитель.',
            ]);
        }

        return $visibility;
    }

    /**
     * @param  array<string, mixed>|null  $sharedWith
     * @return array{role_ids?: list<int>, user_ids?: list<int>}|null
     */
    private function normalizeSharedWith(?array $sharedWith): ?array
    {
        if ($sharedWith === null) {
            return null;
        }

        $roleIds = collect($sharedWith['role_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $userIds = collect($sharedWith['user_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($roleIds === [] && $userIds === []) {
            return null;
        }

        return [
            'role_ids' => $roleIds,
            'user_ids' => $userIds,
        ];
    }

    private function normalizeQuickSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 500);
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('grid_views');
    }

    /**
     * @return Builder<GridView>
     */
    private function visibleQuery(User $user, ?string $gridKey = null): Builder
    {
        $query = GridView::query();

        if ($gridKey !== null) {
            $query->where('grid_key', $gridKey);
        }

        $roleId = (int) ($user->role_id ?? 0);
        $userId = (int) $user->id;

        return $query->where(function (Builder $builder) use ($user, $roleId, $userId, $gridKey): void {
            $builder->where('owner_user_id', $userId)
                ->orWhere(function (Builder $roleScope) use ($roleId): void {
                    $roleScope->where('visibility', 'role')
                        ->where(function (Builder $inner) use ($roleId): void {
                            $inner->whereHas('owner', fn (Builder $ownerQuery): Builder => $ownerQuery->where('role_id', $roleId))
                                ->orWhereJsonContains('shared_with->role_ids', $roleId);
                        });
                })
                ->orWhere(function (Builder $usersScope) use ($userId): void {
                    $usersScope->where('visibility', 'users')
                        ->whereJsonContains('shared_with->user_ids', $userId);
                });

            if ($gridKey !== null && GridViewCatalog::userCanAccessGrid($user, $gridKey)) {
                $builder->orWhere(function (Builder $workspaceScope) use ($gridKey): void {
                    $workspaceScope->where('visibility', 'workspace')
                        ->where('grid_key', $gridKey);
                });
            } elseif ($gridKey === null) {
                $builder->orWhere('visibility', 'workspace');
            }
        });
    }
}
