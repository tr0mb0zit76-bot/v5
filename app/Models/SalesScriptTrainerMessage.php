<?php

namespace App\Models;

use App\Enums\TrainerPeerReaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptTrainerMessage extends Model
{
    protected $fillable = [
        'sales_script_play_session_id',
        'sales_script_node_id',
        'user_id',
        'role',
        'content',
        'peer_reaction',
        'auto_peer_reaction',
        'feedback_tags',
        'step_key',
    ];

    /**
     * @return BelongsTo<SalesScriptPlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SalesScriptPlaySession::class, 'sales_script_play_session_id');
    }

    /**
     * @return BelongsTo<SalesScriptNode, $this>
     */
    public function scriptNode(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'sales_script_node_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'peer_reaction' => TrainerPeerReaction::class,
            'auto_peer_reaction' => TrainerPeerReaction::class,
            'feedback_tags' => 'array',
        ];
    }
}
