<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorEnrichmentRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_CREATE = 'create';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MCP = 'mcp';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'status',
        'trigger',
        'sources_json',
        'dossier_json',
        'proposed_drafts_json',
        'error_message',
        'started_at',
        'finished_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources_json' => 'array',
            'dossier_json' => 'array',
            'proposed_drafts_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }
}
