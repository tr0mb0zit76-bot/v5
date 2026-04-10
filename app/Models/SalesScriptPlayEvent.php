<?php

namespace App\Models;

use App\Enums\SalesPlayEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptPlayEvent extends Model
{
    public $timestamps = false;

    protected $table = 'sales_script_play_events';

    protected $fillable = [
        'sales_script_play_session_id',
        'type',
        'sales_script_node_id',
        'sales_script_reaction_class_id',
        'body',
        'meta',
        'created_at',
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
    public function node(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'sales_script_node_id');
    }

    /**
     * @return BelongsTo<SalesScriptReactionClass, $this>
     */
    public function reactionClass(): BelongsTo
    {
        return $this->belongsTo(SalesScriptReactionClass::class, 'sales_script_reaction_class_id');
    }

    protected function casts(): array
    {
        return [
            'type' => SalesPlayEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
