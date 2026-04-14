<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class Contractor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'full_name',
        'short_description',
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
        'signer_name_nominative',
        'signer_name_prepositional',
        'signer_authority_basis',
        'bank_name',
        'bik',
        'account_number',
        'correspondent_account',
        'bank_accounts',
        'ati_profiles',
        'ati_id',
        'transport_requirements',
        'specializations',
        'activity_types',
        'rating',
        'completed_orders',
        'metadata',
        'debt_limit',
        'debt_limit_currency',
        'stop_on_limit',
        'default_customer_payment_form',
        'default_customer_payment_term',
        'default_customer_payment_schedule',
        'default_carrier_payment_form',
        'default_carrier_payment_term',
        'default_carrier_payment_schedule',
        'cooperation_terms_notes',
        'is_active',
        'is_verified',
        'is_own_company',
        'is_non_resident',
        'owner_id',
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
            'activity_types' => 'array',
            'metadata' => 'array',
            'bank_accounts' => 'json:unicode',
            'debt_limit' => 'decimal:2',
            'default_customer_payment_schedule' => 'json:unicode',
            'default_carrier_payment_schedule' => 'json:unicode',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_own_company' => 'boolean',
            'is_non_resident' => 'boolean',
            'stop_on_limit' => 'boolean',
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
        $relation = $this->hasMany(Order::class, 'customer_id');

        if (! Schema::hasColumn($relation->getRelated()->getTable(), 'deleted_at')) {
            return $relation->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $relation;
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function carrierOrders(): HasMany
    {
        $relation = $this->hasMany(Order::class, 'carrier_id');

        if (! Schema::hasColumn($relation->getRelated()->getTable(), 'deleted_at')) {
            return $relation->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $relation;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Scope a query to apply visibility rules based on user role.
     *
     * @param  Builder  $query
     * @param  string|null  $typeFilter  Optional type filter ('customer', 'carrier', 'both')
     * @return Builder
     */
    public function scopeVisibleTo($query, ?User $user = null, ?string $typeFilter = null)
    {
        if (! $user) {
            return $query;
        }

        // Check user's role to determine visibility
        // If user has admin role or can view all contractors, show all
        $role = $user->role;
        if ($role && ($role->name === 'admin' || (is_array($role->permissions) && in_array('view_all_contractors', $role->permissions)))) {
            // Admin can see all, but still apply type filter if specified
            if (in_array($typeFilter, ['customer', 'carrier', 'both'])) {
                $query->where('type', $typeFilter);
            }

            return $query;
        }

        // For non-admin users (managers):
        // Apply visibility rules based on type filter
        return $query->where(function ($q) use ($user, $typeFilter) {
            // If filtering by specific type
            if (in_array($typeFilter, ['customer', 'carrier', 'both'])) {
                if ($typeFilter === 'customer') {
                    // When filtering by customer type, show all customers
                    // (not just owned ones, because user explicitly wants to see customers)
                    $q->where('type', 'customer');
                } elseif ($typeFilter === 'carrier') {
                    // When filtering by carrier type, show all carriers
                    $q->where('type', 'carrier');
                } else {
                    // When filtering by 'both' type
                    $q->where('type', 'both');
                }
            } else {
                // No type filter - apply standard visibility rules
                // 1. All carriers (type = 'carrier' or 'both') are visible to everyone
                // 2. Only their own customers (type = 'customer' and owner_id = user->id) are visible
                $q->whereIn('type', ['carrier', 'both'])
                    ->orWhere(function ($subQ) use ($user) {
                        $subQ->where('type', 'customer')
                            ->where('owner_id', $user->id);
                    });
            }
        });
    }
}
