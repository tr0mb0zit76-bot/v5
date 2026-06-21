<?php

namespace App\Models;

use App\Enums\SalesScriptNodeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScriptNode extends Model
{
    protected $table = 'sales_script_nodes';

    protected $fillable = [
        'sales_script_version_id',
        'client_key',
        'kind',
        'body',
        'body_variant_b',
        'ab_enabled',
        'ab_variant_b_weight',
        'hint',
        'tags',
        'capture_field_codes',
        'sort_order',
        'canvas_x',
        'canvas_y',
    ];

    /**
     * @return BelongsTo<SalesScriptVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SalesScriptVersion::class, 'sales_script_version_id');
    }

    /**
     * @return HasMany<SalesScriptTransition, $this>
     */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(SalesScriptTransition::class, 'from_node_id');
    }

    /**
     * @return HasMany<SalesScriptTransition, $this>
     */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(SalesScriptTransition::class, 'to_node_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => SalesScriptNodeKind::class,
            'tags' => 'array',
            'capture_field_codes' => 'array',
            'ab_enabled' => 'boolean',
            'ab_variant_b_weight' => 'integer',
            'sort_order' => 'integer',
            'canvas_x' => 'integer',
            'canvas_y' => 'integer',
        ];
    }
}
