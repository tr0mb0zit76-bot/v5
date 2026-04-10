<?php

namespace App\Models;

use App\Enums\SalesPlaySessionOutcome;
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
        'order_id',
        'outcome',
        'primary_reaction_class_id',
        'notes',
        'started_at',
        'completed_at',
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

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'outcome' => SalesPlaySessionOutcome::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
