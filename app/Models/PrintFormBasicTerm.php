<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintFormBasicTerm extends Model
{
    public const PARTY_CUSTOMER = 'customer';

    public const PARTY_CARRIER = 'carrier';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party',
        'contractor_id',
        'sort_order',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public static function placeholderPrefixForParty(string $party): ?string
    {
        return match ($party) {
            self::PARTY_CUSTOMER => 'cp',
            self::PARTY_CARRIER => 'dp',
            default => null,
        };
    }
}
