<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'full_name',
        'inn',
        'kpp',
        'ogrn',
        'okpo',
        'legal_form',
        'legal_address',
        'actual_address',
        'postal_address',
        'phone',
        'email',
        'website',
        'contact_person',
        'contact_person_phone',
        'contact_person_email',
        'contact_person_position',
        'bank_name',
        'bik',
        'account_number',
        'correspondent_account',
        'ati_profiles',
        'ati_id',
        'transport_requirements',
        'specializations',
        'rating',
        'completed_orders',
        'metadata',
        'is_active',
        'is_verified',
        'is_own_company',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ati_profiles' => 'array',
            'transport_requirements' => 'array',
            'specializations' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_own_company' => 'boolean',
            'rating' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<ContractorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ContractorContact::class)->orderByDesc('is_primary')->orderBy('full_name');
    }

    /**
     * @return HasMany<ContractorInteraction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ContractorInteraction::class)->latest('contacted_at');
    }

    /**
     * @return HasMany<ContractorDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ContractorDocument::class)->latest('document_date');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function carrierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'carrier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
