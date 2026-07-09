<?php

namespace App\Models;

use App\Enums\SalesPlaySessionOutcome;
use App\Enums\SalesTrainerDialogQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScriptPlaySession extends Model
{
    protected $table = 'sales_script_play_sessions';

    protected $fillable = [
        'user_id',
        'sales_script_version_id',
        'current_node_id',
        'contractor_id',
        'lead_id',
        'order_id',
        'context_tags',
        'return_stack',
        'is_trainer',
        'trainer_profile_key',
        'trainer_profile_title',
        'trainer_profile_context',
        'training_role_mode',
        'trainer_assistant_instructions',
        'trainer_dialog_quality',
        'trainer_score',
        'outcome',
        'primary_reaction_class_id',
        'notes',
        'started_at',
        'completed_at',
        'crm_synced_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SalesScriptVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SalesScriptVersion::class, 'sales_script_version_id');
    }

    /**
     * @return BelongsTo<SalesScriptNode, $this>
     */
    public function currentNode(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'current_node_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<SalesScriptReactionClass, $this>
     */
    public function primaryReactionClass(): BelongsTo
    {
        return $this->belongsTo(SalesScriptReactionClass::class, 'primary_reaction_class_id');
    }

    /**
     * @return HasMany<SalesScriptPlayEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SalesScriptPlayEvent::class, 'sales_script_play_session_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalesScriptTrainerMessage, $this>
     */
    public function trainerMessages(): HasMany
    {
        return $this->hasMany(SalesScriptTrainerMessage::class, 'sales_script_play_session_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalesScriptPlaySessionFieldValue, $this>
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(SalesScriptPlaySessionFieldValue::class, 'sales_script_play_session_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'outcome' => SalesPlaySessionOutcome::class,
            'context_tags' => 'array',
            'return_stack' => 'array',
            'is_trainer' => 'boolean',
            'trainer_dialog_quality' => SalesTrainerDialogQuality::class,
            'trainer_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'crm_synced_at' => 'datetime',
        ];
    }
}
