<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Services\ContractorCreditService;
use App\Services\ContractorOperationalStatusService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ContractorLimitApprovalService
{
    public const REASON_NEW_CARD = 'new_card';

    public const REASON_VERIFICATION_EXPIRED = 'verification_expired';

    public const REASON_LIMIT_REACHED = 'limit_reached';

    public const REASON_LIMIT_ZERO = 'limit_zero';

    public const REASON_LIMIT_INSUFFICIENT = 'limit_insufficient';

    public function __construct(
        private readonly ContractorOperationalStatusService $statusService,
        private readonly ContractorCreditService $creditService,
        private readonly CabinetNotifier $notifier,
    ) {}

    public function pendingFor(Contractor $contractor): ?ContractorRiskAssessment
    {
        if (! Schema::hasTable('contractor_risk_assessments')) {
            return null;
        }

        return ContractorRiskAssessment::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ContractorRiskAssessment::STATUS_PENDING_APPROVAL)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingPayloadFor(Contractor $contractor): ?array
    {
        $pending = $this->pendingFor($contractor);
        if ($pending === null) {
            return null;
        }

        return [
            'assessment_id' => $pending->id,
            'submitted_at' => $pending->submitted_at?->toIso8601String(),
            'submission_reason' => $pending->submission_reason,
            'submission_reason_label' => $this->reasonLabel($pending->submission_reason),
            'submitted_by_name' => $pending->submitter?->name,
        ];
    }

    public function canSubmit(Contractor $contractor, float $currentDebt): bool
    {
        if ($contractor->isOwnCompanyProfile()) {
            return false;
        }

        if ($this->pendingFor($contractor) !== null) {
            return false;
        }

        return $this->resolveReason($contractor, $currentDebt) !== null;
    }

    public function resolveReason(Contractor $contractor, float $currentDebt): ?string
    {
        if ($contractor->isOwnCompanyProfile()) {
            return null;
        }

        if ($this->creditService->isBlockedByDebtLimit($contractor, $currentDebt)) {
            return self::REASON_LIMIT_REACHED;
        }

        $debtLimit = is_numeric($contractor->debt_limit) ? (float) $contractor->debt_limit : null;
        if ($debtLimit === null || $debtLimit <= 0) {
            return self::REASON_LIMIT_ZERO;
        }

        if (! $contractor->is_verified) {
            if ($contractor->verified_at !== null && $this->statusService->isVerificationExpired($contractor->verified_at)) {
                return self::REASON_VERIFICATION_EXPIRED;
            }

            return self::REASON_NEW_CARD;
        }

        if ($this->statusService->isVerificationExpired($contractor->verified_at)) {
            return self::REASON_VERIFICATION_EXPIRED;
        }

        if ($debtLimit > 0 && $currentDebt > 0) {
            $utilization = $currentDebt / $debtLimit;
            if ($utilization >= 0.85) {
                return self::REASON_LIMIT_INSUFFICIENT;
            }
        }

        return null;
    }

    public function submit(Contractor $contractor, User $requester, ?string $reason = null): ContractorRiskAssessment
    {
        if ($contractor->isOwnCompanyProfile()) {
            throw ValidationException::withMessages([
                'contractor' => 'Согласование лимита недоступно для своей компании.',
            ]);
        }

        if (! Schema::hasTable('contractor_risk_assessments')) {
            throw ValidationException::withMessages([
                'contractor' => 'Модуль оценки риска не настроен.',
            ]);
        }

        if ($this->pendingFor($contractor) !== null) {
            throw ValidationException::withMessages([
                'contractor' => 'Запрос на согласование уже отправлен.',
            ]);
        }

        $currentDebt = $this->creditService->currentDebtForContractor($contractor->id);
        $reason ??= $this->resolveReason($contractor, $currentDebt);

        if ($reason === null) {
            throw ValidationException::withMessages([
                'contractor' => 'Сейчас нет оснований для отправки на согласование.',
            ]);
        }

        $assessment = ContractorRiskAssessment::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ContractorRiskAssessment::STATUS_DRAFT)
            ->latest('id')
            ->first();

        if ($assessment === null) {
            $assessment = ContractorRiskAssessment::query()->create([
                'contractor_id' => $contractor->id,
                'model_version' => (string) config('contractor_scoring.model_version', '2.0'),
                'status' => ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'submitted_by' => $requester->id,
                'submission_reason' => $reason,
            ]);
        } else {
            $assessment->forceFill([
                'status' => ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'submitted_by' => $requester->id,
                'submission_reason' => $reason,
            ])->save();
        }

        $this->notifier->notifyContractorLimitApprovalRequested($contractor, $assessment, $requester);

        return $assessment->refresh();
    }

    public function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            self::REASON_NEW_CARD => 'Новая карточка / не проверен',
            self::REASON_VERIFICATION_EXPIRED => 'Проверка истекла',
            self::REASON_LIMIT_REACHED => 'Лимит достигнут',
            self::REASON_LIMIT_ZERO => 'Лимит не задан или обнулён',
            self::REASON_LIMIT_INSUFFICIENT => 'Лимита не хватает',
            default => 'Согласование лимита',
        };
    }
}
