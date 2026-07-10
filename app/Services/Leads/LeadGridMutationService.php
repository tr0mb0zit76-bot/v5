<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\User;
use App\Support\LeadSource;
use App\Support\LeadStatus;
use App\Support\LeadViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\Schema;

final class LeadGridMutationService
{
    /**
     * @return list<string>
     */
    public function inlineEditableFields(Lead $lead, User $user): array
    {
        $fields = ['source'];

        if ($this->canChangeResponsible($user, $lead)) {
            $fields[] = 'responsible_id';
        }

        if ($this->canChangeStatusInline($lead)) {
            $fields[] = 'status';
        }

        return $fields;
    }

    public function canChangeStatusInline(Lead $lead): bool
    {
        if (LeadStatus::isClosed($lead->status)) {
            return false;
        }

        if (Schema::hasColumn('leads', 'business_process_id') && $lead->business_process_id !== null) {
            return false;
        }

        return true;
    }

    public function canChangeResponsible(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->canAssignResponsible($user)) {
            return true;
        }

        return (int) $lead->responsible_id === (int) $user->id;
    }

    public function canAssignResponsible(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');
    }

    /**
     * @return array{updated: bool, skipped: bool, message: string|null}
     */
    public function applyField(Lead $lead, User $user, string $field, mixed $value): array
    {
        if (! in_array($field, $this->inlineEditableFields($lead, $user), true)) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Поле недоступно для редактирования.',
            ];
        }

        return match ($field) {
            'source' => $this->applySource($lead, $user, $value),
            'responsible_id' => $this->applyResponsible($lead, $user, $value),
            'status' => $this->applyStatus($lead, $user, $value),
            default => [
                'updated' => false,
                'skipped' => true,
                'message' => 'Неизвестное поле.',
            ],
        };
    }

    /**
     * @param  list<int>  $leadIds
     * @return array{updated_count: int, skipped_count: int, messages: list<string>}
     */
    public function massApply(User $user, array $leadIds, string $action, mixed $value): array
    {
        $updatedCount = 0;
        $skippedCount = 0;
        $messages = [];

        $leads = Lead::query()
            ->withoutTrashed()
            ->whereIn('id', $leadIds)
            ->get();

        foreach ($leads as $lead) {
            if (! $this->canAccessLead($user, $lead)) {
                $skippedCount++;

                continue;
            }

            if ($action === 'delete') {
                if (! $lead->trashed()) {
                    $lead->delete();
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }

                continue;
            }

            $field = match ($action) {
                'source' => 'source',
                'responsible_id', 'assign' => 'responsible_id',
                'status' => 'status',
                default => null,
            };

            if ($field === null) {
                $skippedCount++;

                continue;
            }

            $result = $this->applyField($lead, $user, $field, $value);

            if ($result['updated']) {
                $updatedCount++;
            } else {
                $skippedCount++;
                if ($result['message'] !== null) {
                    $messages[] = $result['message'];
                }
            }
        }

        return [
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'messages' => array_values(array_unique($messages)),
        ];
    }

    public function canAccessLead(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }

    /**
     * @return array{updated: bool, skipped: bool, message: string|null}
     */
    private function applySource(Lead $lead, User $user, mixed $value): array
    {
        $source = is_string($value) ? trim($value) : '';
        $source = $source === '' ? null : $source;

        if ($source !== null && ! in_array($source, LeadSource::values(), true)) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Неизвестный источник.',
            ];
        }

        $lead->update([
            'source' => $source,
            'updated_by' => $user->id,
        ]);

        return ['updated' => true, 'skipped' => false, 'message' => null];
    }

    /**
     * @return array{updated: bool, skipped: bool, message: string|null}
     */
    private function applyResponsible(Lead $lead, User $user, mixed $value): array
    {
        if (! $this->canChangeResponsible($user, $lead)) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Недостаточно прав для смены ответственного.',
            ];
        }

        $responsibleId = $value === null || $value === '' ? null : (int) $value;

        if ($responsibleId === null) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Укажите ответственного.',
            ];
        }

        $lead->update([
            'responsible_id' => $responsibleId,
            'updated_by' => $user->id,
        ]);

        return ['updated' => true, 'skipped' => false, 'message' => null];
    }

    /**
     * @return array{updated: bool, skipped: bool, message: string|null}
     */
    private function applyStatus(Lead $lead, User $user, mixed $value): array
    {
        if (! $this->canChangeStatusInline($lead)) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Статус лида с бизнес-процессом меняется только в карточке.',
            ];
        }

        $status = is_string($value) ? $value : '';

        if (! in_array($status, LeadStatus::inlineEditableValues(), true)) {
            return [
                'updated' => false,
                'skipped' => true,
                'message' => 'Этот статус нельзя выбрать из грида.',
            ];
        }

        if ($status === $lead->status) {
            return ['updated' => true, 'skipped' => false, 'message' => null];
        }

        $previousStatus = $lead->status;

        $lead->update([
            'status' => $status,
            'updated_by' => $user->id,
        ]);

        $lead->activities()->create([
            'type' => 'status_change',
            'subject' => 'Статус лида обновлён',
            'content' => sprintf(
                'Переведён в статус «%s»',
                LeadStatus::label($lead->status),
            ),
            'created_by' => $user->id,
        ]);

        if ($previousStatus !== 'lost' && $status === 'lost') {
            app(LeadLinkedTaskService::class)->cancelOpenTasksForLostLead($lead->fresh(), $user);
        }

        return ['updated' => true, 'skipped' => false, 'message' => null];
    }
}
