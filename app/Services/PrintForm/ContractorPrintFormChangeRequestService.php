<?php

namespace App\Services\PrintForm;

use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Services\Notifications\NotificationRecipientResolver;
use App\Support\PrintFormBasicTermsTableCloner;
use App\Support\RoleAccess;
use App\Support\TaskNumberGenerator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ContractorPrintFormChangeRequestService
{
    public function __construct(
        private readonly PrintFormBasicTermsService $basicTermsService,
        private readonly TaskNumberGenerator $taskNumbers,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly CabinetNotifier $notifier,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('contractor_print_form_change_requests');
    }

    public function canDirectManagePrintForm(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return RoleAccess::canAccessSettingsSystem($user);
    }

    public function canApprovePrintFormChanges(User $user): bool
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! Schema::hasTable('department_user')) {
            return false;
        }

        return $user->departments()
            ->where('department_user.receives_approvals', true)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingPayloadForContractor(int $contractorId): ?array
    {
        $pending = $this->pendingForContractor($contractorId);

        return $pending !== null ? $this->serializeRequest($pending) : null;
    }

    public function pendingForContractor(int $contractorId): ?ContractorPrintFormChangeRequest
    {
        if (! $this->tablesReady()) {
            return null;
        }

        return ContractorPrintFormChangeRequest::query()
            ->where('contractor_id', $contractorId)
            ->where('status', ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL)
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<string>  $items
     */
    public function syncBasicTermsDirectly(
        Contractor $contractor,
        string $party,
        array $items,
        User $actor,
    ): void {
        $this->assertParty($party);
        $this->basicTermsService->sync($party, (int) $contractor->id, $items);
    }

    /**
     * @param  list<string>  $items
     */
    public function submitBasicTermsChange(
        Contractor $contractor,
        string $party,
        array $items,
        User $requester,
        ?string $managerNotes = null,
        ?string $yurikSummary = null,
    ): ContractorPrintFormChangeRequest {
        $this->assertParty($party);

        if (! $this->tablesReady()) {
            throw ValidationException::withMessages([
                'contractor' => 'Модуль согласования форм не настроен.',
            ]);
        }

        if ($this->pendingForContractor((int) $contractor->id) !== null) {
            throw ValidationException::withMessages([
                'contractor' => 'У контрагента уже есть заявка на согласование формы.',
            ]);
        }

        $normalized = $this->normalizeItems($items);

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'items' => 'Добавьте хотя бы один пункт базовых условий.',
            ]);
        }

        $request = ContractorPrintFormChangeRequest::query()->create([
            'contractor_id' => $contractor->id,
            'party' => $party,
            'change_type' => ContractorPrintFormChangeRequest::CHANGE_TYPE_BASIC_TERMS,
            'status' => ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL,
            'payload' => [
                'items' => $normalized,
            ],
            'manager_notes' => $this->nullIfBlank($managerNotes),
            'yurik_summary' => $this->nullIfBlank($yurikSummary),
            'submitted_by' => $requester->id,
            'submitted_at' => now(),
        ]);

        $task = $this->createApprovalTask($contractor, $request, $requester);

        if ($task !== null) {
            $request->forceFill(['task_id' => $task->id])->save();
        }

        $this->notifier->notifyContractorPrintFormChangeRequested($contractor, $request->fresh(), $requester);

        return $request->refresh();
    }

    public function approve(ContractorPrintFormChangeRequest $changeRequest, User $reviewer): ContractorPrintFormChangeRequest
    {
        $this->assertCanReview($reviewer, $changeRequest);

        if ($changeRequest->status !== ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'status' => 'Заявка уже обработана.',
            ]);
        }

        if ($changeRequest->change_type === ContractorPrintFormChangeRequest::CHANGE_TYPE_BASIC_TERMS) {
            $items = $this->itemsFromPayload($changeRequest);
            $this->basicTermsService->sync(
                (string) $changeRequest->party,
                (int) $changeRequest->contractor_id,
                $items,
            );
        }

        $changeRequest->forceFill([
            'status' => ContractorPrintFormChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->completeLinkedTask($changeRequest, $reviewer);

        return $changeRequest->refresh();
    }

    public function reject(
        ContractorPrintFormChangeRequest $changeRequest,
        User $reviewer,
        string $reason,
    ): ContractorPrintFormChangeRequest {
        $this->assertCanReview($reviewer, $changeRequest);

        if ($changeRequest->status !== ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'status' => 'Заявка уже обработана.',
            ]);
        }

        $changeRequest->forceFill([
            'status' => ContractorPrintFormChangeRequest::STATUS_REJECTED,
            'rejection_reason' => trim($reason),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->completeLinkedTask($changeRequest, $reviewer);

        return $changeRequest->refresh();
    }

    public function markNeedsCounterparty(
        ContractorPrintFormChangeRequest $changeRequest,
        User $reviewer,
        ?string $notes = null,
    ): ContractorPrintFormChangeRequest {
        $this->assertCanReview($reviewer, $changeRequest);

        if ($changeRequest->status !== ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'status' => 'Заявка уже обработана.',
            ]);
        }

        $changeRequest->forceFill([
            'status' => ContractorPrintFormChangeRequest::STATUS_NEEDS_COUNTERPARTY,
            'rejection_reason' => $this->nullIfBlank($notes),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->completeLinkedTask($changeRequest, $reviewer);

        return $changeRequest->refresh();
    }

    /**
     * @return array{
     *     enabled: bool,
     *     can_direct_manage: bool,
     *     can_approve: bool,
     *     active_party: string,
     *     party_options: list<array{value: string, label: string}>,
     *     placeholder_help: array<string, mixed>,
     *     customer: array{rows: list<array{id: int|null, body: string, sort_order: int}>, mode: array{mode: string, label: string}},
     *     carrier: array{rows: list<array{id: int|null, body: string, sort_order: int}>, mode: array{mode: string, label: string}},
     *     external_templates: list<array<string, mixed>>,
     *     pending_change: array<string, mixed>|null
     * }
     */
    public function editorPayloadForContractor(Contractor $contractor, ?User $user, string $activeParty): array
    {
        $activeParty = in_array($activeParty, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)
            ? $activeParty
            : PrintFormBasicTerm::PARTY_CUSTOMER;

        $profileResolver = app(ContractorPrintFormProfileResolver::class);
        $profile = $profileResolver->resolve($contractor);

        return [
            'enabled' => $this->basicTermsService->tablesReady(),
            'can_direct_manage' => $this->canDirectManagePrintForm($user),
            'can_approve' => $user !== null && $this->canApprovePrintFormChanges($user),
            'active_party' => $activeParty,
            'party_options' => [
                ['value' => PrintFormBasicTerm::PARTY_CUSTOMER, 'label' => 'Заказчик (cp_*)'],
                ['value' => PrintFormBasicTerm::PARTY_CARRIER, 'label' => 'Перевозчик (dp_*)'],
            ],
            'placeholder_help' => [
                PrintFormBasicTerm::PARTY_CUSTOMER => PrintFormBasicTermsTableCloner::placeholderHelpForParty(PrintFormBasicTerm::PARTY_CUSTOMER),
                PrintFormBasicTerm::PARTY_CARRIER => PrintFormBasicTermsTableCloner::placeholderHelpForParty(PrintFormBasicTerm::PARTY_CARRIER),
            ],
            'customer' => [
                'rows' => $this->basicTermsService->listRows(PrintFormBasicTerm::PARTY_CUSTOMER, (int) $contractor->id),
                'mode' => $profile['customer'],
            ],
            'carrier' => [
                'rows' => $this->basicTermsService->listRows(PrintFormBasicTerm::PARTY_CARRIER, (int) $contractor->id),
                'mode' => $profile['carrier'],
            ],
            'external_templates' => $this->externalTemplatesForContractor((int) $contractor->id),
            'pending_change' => $this->pendingPayloadForContractor((int) $contractor->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(ContractorPrintFormChangeRequest $changeRequest): array
    {
        return [
            'id' => $changeRequest->id,
            'contractor_id' => $changeRequest->contractor_id,
            'party' => $changeRequest->party,
            'party_label' => $changeRequest->party === PrintFormBasicTerm::PARTY_CARRIER ? 'перевозчик' : 'заказчик',
            'change_type' => $changeRequest->change_type,
            'status' => $changeRequest->status,
            'status_label' => ContractorPrintFormChangeRequest::statusLabel((string) $changeRequest->status),
            'payload' => $changeRequest->payload,
            'manager_notes' => $changeRequest->manager_notes,
            'yurik_summary' => $changeRequest->yurik_summary,
            'rejection_reason' => $changeRequest->rejection_reason,
            'submitted_at' => $changeRequest->submitted_at?->toIso8601String(),
            'submitted_by_name' => $changeRequest->submitter?->name,
            'reviewed_at' => $changeRequest->reviewed_at?->toIso8601String(),
            'reviewed_by_name' => $changeRequest->reviewer?->name,
            'task_id' => $changeRequest->task_id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function externalTemplatesForContractor(int $contractorId): array
    {
        if (! Schema::hasTable('print_form_templates')) {
            return [];
        }

        return PrintFormTemplate::query()
            ->where('contractor_id', $contractorId)
            ->where('source_type', 'external_docx')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'party', 'document_type', 'updated_at'])
            ->map(fn (PrintFormTemplate $template): array => [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'party' => $template->party,
                'document_type' => $template->document_type,
                'updated_at' => $template->updated_at?->toIso8601String(),
                'settings_url' => route('settings.templates.index', [], false),
            ])
            ->values()
            ->all();
    }

    private function createApprovalTask(
        Contractor $contractor,
        ContractorPrintFormChangeRequest $changeRequest,
        User $requester,
    ): ?Task {
        if (! Schema::hasTable('tasks')) {
            return null;
        }

        $recipient = $this->recipientResolver
            ->approvalRecipientsForUser($requester, $requester)
            ->first();

        if ($recipient === null) {
            return null;
        }

        $partyLabel = $changeRequest->party === PrintFormBasicTerm::PARTY_CARRIER ? 'перевозчика' : 'заказчика';

        return Task::query()->create([
            'number' => $this->taskNumbers->next(),
            'title' => 'Согласовать базовые условия '.$partyLabel.': '.$contractor->name,
            'description' => trim(collect([
                $changeRequest->manager_notes,
                $changeRequest->yurik_summary,
            ])->filter()->implode("\n\n")),
            'status' => 'new',
            'priority' => 'high',
            'responsible_id' => $recipient->id,
            'created_by' => $requester->id,
            'contractor_id' => $contractor->id,
            'meta' => [
                'contractor_print_form_change_request_id' => $changeRequest->id,
                'party' => $changeRequest->party,
            ],
        ]);
    }

    private function completeLinkedTask(ContractorPrintFormChangeRequest $changeRequest, User $reviewer): void
    {
        if ($changeRequest->task_id === null || ! Schema::hasTable('tasks')) {
            return;
        }

        Task::query()
            ->whereKey($changeRequest->task_id)
            ->whereNull('completed_at')
            ->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
    }

    private function assertCanReview(User $reviewer, ContractorPrintFormChangeRequest $changeRequest): void
    {
        if (! $this->canApprovePrintFormChanges($reviewer)) {
            throw ValidationException::withMessages([
                'user' => 'Нет прав на согласование изменений формы.',
            ]);
        }
    }

    private function assertParty(string $party): void
    {
        if (! in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            throw ValidationException::withMessages([
                'party' => 'Недопустимая сторона для базовых условий.',
            ]);
        }
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $body): string => trim((string) $body))
            ->filter(fn (string $body): bool => $body !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function itemsFromPayload(ContractorPrintFormChangeRequest $changeRequest): array
    {
        $payload = is_array($changeRequest->payload) ? $changeRequest->payload : [];
        $items = $payload['items'] ?? [];

        return is_array($items) ? $this->normalizeItems($items) : [];
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
