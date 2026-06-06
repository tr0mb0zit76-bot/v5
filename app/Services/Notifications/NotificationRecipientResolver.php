<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class NotificationRecipientResolver
{
    /**
     * @return EloquentCollection<int, User>
     */
    public function approvalRecipientsForUser(User $contextUser, ?User $exclude = null): EloquentCollection
    {
        $departmentId = $this->primaryDepartmentIdForUser($contextUser);

        return $this->approvalRecipientsForDepartment($departmentId, $exclude);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function approvalRecipientsForOrder(Order $order, User $requester): EloquentCollection
    {
        $contextUser = $order->relationLoaded('manager')
            ? $order->manager
            : User::query()->find($order->manager_id);

        $departmentId = $contextUser !== null
            ? $this->primaryDepartmentIdForUser($contextUser)
            : $this->primaryDepartmentIdForUser($requester);

        $recipients = $this->approvalRecipientsForDepartment($departmentId, $requester);

        $signingRecipients = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $requester->id)
            ->with('signingOwnCompanies:id')
            ->get()
            ->filter(fn (User $user): bool => $user->canSignDocumentsForOwnCompany($order->own_company_id))
            ->values();

        return $recipients
            ->merge($signingRecipients)
            ->unique('id')
            ->values();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function approvalRecipientsForDepartment(?int $departmentId, ?User $exclude = null): EloquentCollection
    {
        if (! Schema::hasTable('department_user') || $departmentId === null || $departmentId <= 0) {
            return $this->fallbackApprovalRecipients($exclude);
        }

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('departments', function ($builder) use ($departmentId): void {
                $builder
                    ->where('departments.id', $departmentId)
                    ->where('department_user.receives_approvals', true);
            });

        if ($exclude !== null) {
            $query->where('id', '!=', $exclude->id);
        }

        $recipients = $query->get();

        if ($recipients->isNotEmpty()) {
            return $recipients;
        }

        return $this->fallbackApprovalRecipients($exclude);
    }

    public function primaryDepartmentIdForUser(User $user): ?int
    {
        if (! Schema::hasTable('department_user')) {
            return null;
        }

        if ($user->relationLoaded('departments')) {
            $primary = $user->departments->first(fn ($department) => (bool) $department->pivot->is_primary);

            if ($primary !== null) {
                return (int) $primary->id;
            }

            $first = $user->departments->first();

            return $first !== null ? (int) $first->id : null;
        }

        $primaryDepartmentId = $user->departments()
            ->wherePivot('is_primary', true)
            ->value('departments.id');

        if ($primaryDepartmentId !== null) {
            return (int) $primaryDepartmentId;
        }

        $fallbackDepartmentId = $user->departments()->value('departments.id');

        return $fallbackDepartmentId !== null ? (int) $fallbackDepartmentId : null;
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function fallbackApprovalRecipients(?User $exclude): EloquentCollection
    {
        if (! (bool) config('notifications.approval_include_admins', false)) {
            return new EloquentCollection;
        }

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($builder) => $builder->where('name', 'admin'));

        if ($exclude !== null) {
            $query->where('id', '!=', $exclude->id);
        }

        return $query->get();
    }
}
