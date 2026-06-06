<?php

namespace App\Services\Checko;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\ContractorRiskSnapshot;
use App\Models\User;
use App\Services\ContractorOperationalStatusService;
use Illuminate\Support\Carbon;

class ContractorRiskAssessmentService
{
    public function __construct(
        private readonly ContractorOperationalStatusService $statusService,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $scoringResult
     */
    public function recordSnapshotAndDraft(
        Contractor $contractor,
        string $inn,
        array $normalized,
        array $scoringResult,
        bool $fromCache,
    ): ContractorRiskAssessment {
        $modelVersion = (string) config('contractor_scoring.model_version');
        $ttl = (int) config('checko.cache_ttl_seconds');

        $snapshot = ContractorRiskSnapshot::query()->create([
            'contractor_id' => $contractor->id,
            'inn' => $inn,
            'model_version' => $modelVersion,
            'normalized_data' => $normalized,
            'scoring_result' => $scoringResult,
            'checko_from_cache' => $fromCache,
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);

        ContractorRiskAssessment::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [
                ContractorRiskAssessment::STATUS_DRAFT,
                ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
            ])
            ->update(['status' => ContractorRiskAssessment::STATUS_REJECTED, 'outcome' => ContractorRiskAssessment::OUTCOME_REJECTED]);

        return ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'contractor_risk_snapshot_id' => $snapshot->id,
            'model_version' => $modelVersion,
            'status' => ContractorRiskAssessment::STATUS_DRAFT,
            'draft_score' => $scoringResult['score'] ?? null,
            'draft_grade' => $scoringResult['grade'] ?? null,
            'draft_tier' => $scoringResult['tier'] ?? null,
            'draft_recommended_debt_limit_rub' => $scoringResult['recommended_debt_limit_rub'] ?? null,
            'draft_recommended_postpayment_days' => $scoringResult['recommended_postpayment_days'] ?? null,
        ]);
    }

    /**
     * @return array{assessment: ContractorRiskAssessment, verification: array<string, mixed>}
     */
    public function confirm(
        Contractor $contractor,
        ContractorRiskAssessment $assessment,
        User $user,
        string $outcome,
        float $appliedDebtLimit,
        int $appliedPostpaymentDays,
        string $scheduleTarget,
    ): array {
        if ($assessment->contractor_id !== $contractor->id) {
            abort(404);
        }

        if (! in_array($assessment->status, [
            ContractorRiskAssessment::STATUS_DRAFT,
            ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
        ], true)) {
            abort(422, 'Черновик оценки уже обработан.');
        }

        $draftLimit = (int) ($assessment->draft_recommended_debt_limit_rub ?? 0);
        $draftDays = (int) ($assessment->draft_recommended_postpayment_days ?? 0);

        $editDelta = null;
        if ($outcome === ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS) {
            $appliedDebtLimit = (float) $draftLimit;
            $appliedPostpaymentDays = $draftDays;
        } elseif ($outcome === ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS) {
            $editDelta = [
                'debt_limit_rub' => [
                    'draft' => $draftLimit,
                    'applied' => $appliedDebtLimit,
                ],
                'postpayment_days' => [
                    'draft' => $draftDays,
                    'applied' => $appliedPostpaymentDays,
                ],
            ];
        }

        $status = $outcome === ContractorRiskAssessment::OUTCOME_REJECTED
            ? ContractorRiskAssessment::STATUS_REJECTED
            : ContractorRiskAssessment::STATUS_APPROVED;

        $assessment->forceFill([
            'status' => $status,
            'outcome' => $outcome,
            'applied_debt_limit_rub' => $outcome === ContractorRiskAssessment::OUTCOME_REJECTED ? null : $appliedDebtLimit,
            'applied_postpayment_days' => $outcome === ContractorRiskAssessment::OUTCOME_REJECTED ? null : $appliedPostpaymentDays,
            'applied_schedule_target' => $outcome === ContractorRiskAssessment::OUTCOME_REJECTED ? null : $scheduleTarget,
            'edit_delta' => $editDelta,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ])->save();

        if ($outcome !== ContractorRiskAssessment::OUTCOME_REJECTED) {
            $contractor->forceFill(['debt_limit' => $appliedDebtLimit])->save();

            $scheduleField = $scheduleTarget === 'carrier'
                ? 'default_carrier_payment_schedule'
                : 'default_customer_payment_schedule';

            $schedule = is_array($contractor->{$scheduleField}) ? $contractor->{$scheduleField} : [];
            $schedule['postpayment_days'] = $appliedPostpaymentDays;
            $contractor->forceFill([$scheduleField => $schedule])->save();

            $this->statusService->markVerifiedFromScoring($contractor, [
                'score' => $assessment->draft_score,
                'grade' => $assessment->draft_grade,
                'tier' => $assessment->draft_tier,
            ]);
        }

        $contractor->refresh();

        return [
            'assessment' => $assessment->refresh(),
            'verification' => [
                'is_verified' => (bool) $contractor->is_verified,
                'verified_at' => $contractor->verified_at?->toIso8601String(),
            ],
        ];
    }
}
