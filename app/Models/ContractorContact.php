<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractorContact extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'full_name',
        'position',
        'phone',
        'email',
        'is_primary',
        'is_traklo_primary',
        'is_decision_maker',
        'role_in_deal',
        'communication_notes',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_traklo_primary' => 'boolean',
            'is_decision_maker' => 'boolean',
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
     * @return HasOne<User, $this>
     */
    public function externalUser(): HasOne
    {
        return $this->hasOne(User::class, 'contractor_contact_id');
    }
}
