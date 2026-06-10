<?php

namespace App\Models;

use App\Enums\SalesScriptNodeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptNodeTemplate extends Model
{
    protected $table = 'sales_script_node_templates';

    protected $fillable = [
        'title',
        'kind',
        'body',
        'hint',
        'tags',
        'capture_field_codes',
        'default_transitions',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'kind' => SalesScriptNodeKind::class,
            'tags' => 'array',
            'capture_field_codes' => 'array',
            'default_transitions' => 'array',
        ];
    }
}
