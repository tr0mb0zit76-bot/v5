<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorRiskAssessment extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const OUTCOME_ACCEPTED_AS_IS = 'accepted_as_is';

    public const OUTCOME_ACCEPTED_WITH_EDITS = 'accepted_with_edits';

    public const OUTCOME_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'contractor_risk_snapshot_id',
        'model_version',
        'status',
        'outcome',
        'draft_score',
        'draft_grade',
        'draft_tier',
        'draft_recommended_debt_limit_rub',
        'draft_recommended_postpayment_days',
        'applied_debt_limit_rub',
        'applied_postpayment_days',
        'applied_schedule_target',
        'edit_delta',
        'approved_by',
        'approved_at',
        'submitted_at',
        'submitted_by',
        'submission_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edit_delta' => 'array',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<ContractorRiskSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ContractorRiskSnapshot::class, 'contractor_risk_snapshot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
