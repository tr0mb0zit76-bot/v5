<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptTransition extends Model
{
    protected $table = 'sales_script_transitions';

    protected $fillable = [
        'sales_script_version_id',
        'from_node_id',
        'to_node_id',
        'sales_script_reaction_class_id',
        'sort_order',
    ];

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
    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'from_node_id');
    }

    /**
     * @return BelongsTo<SalesScriptNode, $this>
     */
    public function toNode(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'to_node_id');
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
            'sort_order' => 'integer',
        ];
    }
}
