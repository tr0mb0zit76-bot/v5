<?php

namespace App\Models;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNumberingRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'cipher',
        'own_company_id',
        'separator',
        'prefix_type',
        'prefix_value',
        'body_type',
        'body_value',
        'suffix_type',
        'suffix_value',
        'sequence_pad',
        'sequence_scope',
        'sequence_counters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'own_company_id' => 'integer',
            'sequence_pad' => 'integer',
            'sequence_counters' => 'array',
            'prefix_type' => OrderNumberSegmentType::class,
            'body_type' => OrderNumberSegmentType::class,
            'suffix_type' => OrderNumberSegmentType::class,
            'sequence_scope' => OrderNumberSequenceScope::class,
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function ownCompany(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'own_company_id');
    }
}
