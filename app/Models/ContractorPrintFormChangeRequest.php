<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorPrintFormChangeRequest extends Model
{
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_NEEDS_COUNTERPARTY = 'needs_counterparty';

    public const CHANGE_TYPE_BASIC_TERMS = 'basic_terms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'party',
        'change_type',
        'status',
        'payload',
        'manager_notes',
        'yurik_summary',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'task_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_APPROVAL => 'На согласовании',
            self::STATUS_APPROVED => 'Утверждено',
            self::STATUS_REJECTED => 'Отклонено',
            self::STATUS_NEEDS_COUNTERPARTY => 'Согласование с контрагентом',
            default => $status,
        };
    }
}
