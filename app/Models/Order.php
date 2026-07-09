<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'company_code',
        'manager_id',
        'order_owner_id',
        'dispatcher_id',
        'order_date',
        'loading_date',
        'unloading_date',
        'customer_rate',
        'customer_payment_form',
        'customer_payment_term',
        'payment_terms',
        'carrier_rate',
        'carrier_payment_form',
        'carrier_payment_term',
        'additional_expenses',
        'additional_expenses_payment_date',
        'insurance',
        'bonus',
        'kpi_percent',
        'delta',
        'salary_accrued',
        'salary_paid',
        'status',
        'manual_status',
        'status_updated_by',
        'status_updated_at',
        'is_active',
        'lead_id',
        'customer_id',
        'own_company_id',
        'own_company_bank_account_id',
        'carrier_id',
        'driver_id',
        'ai_draft_id',
        'ai_confidence',
        'ai_metadata',
        'ati_response',
        'ati_load_id',
        'ati_published_at',
        'invoice_number',
        'upd_number',
        'waybill_number',
        'track_number_customer',
        'track_sent_date_customer',
        'track_received_date_customer',
        'track_number_carrier',
        'track_sent_date_carrier',
        'track_received_date_carrier',
        'order_customer_number',
        'order_customer_date',
        'order_carrier_number',
        'order_carrier_date',
        'upd_carrier_number',
        'upd_carrier_date',
        'customer_contact_name',
        'customer_contact_phone',
        'customer_contact_email',
        'carrier_contact_name',
        'carrier_contact_phone',
        'carrier_contact_email',
        'created_by',
        'updated_by',
        'metadata',
        'payment_statuses',
        'payment_status',
        'special_notes',
        'customer_basic_terms',
        'carrier_basic_terms',
        'svh_name',
        'svh_address',
        'customs_post_code',
        'customs_post_name',
        'customs_declaration_place',
        'customs_commodity_code',
        'cargo_declared_sum',
        'is_international_transport',
        'performers',
        'accounting_handoff_at',
        'accounting_handoff_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if ($order->salary_accrued === null) {
                $order->salary_accrued = 0;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'order_date' => 'date',
            'loading_date' => 'date',
            'unloading_date' => 'date',
            'status_updated_at' => 'datetime',
            'ati_published_at' => 'datetime',
            'track_sent_date_customer' => 'date',
            'track_received_date_customer' => 'date',
            'track_sent_date_carrier' => 'date',
            'track_received_date_carrier' => 'date',
            'order_customer_date' => 'date',
            'order_carrier_date' => 'date',
            'upd_carrier_date' => 'date',
            'is_active' => 'boolean',
            'is_international_transport' => 'boolean',
            'ai_metadata' => 'array',
            'ati_response' => 'array',
            'metadata' => 'array',
            'payment_statuses' => 'array',
            'payment_status' => 'string',
            'customer_rate' => 'decimal:2',
            'additional_expenses' => 'decimal:2',
            'additional_expenses_payment_date' => 'date',
            'insurance' => 'decimal:2',
            'cargo_declared_sum' => 'decimal:2',
            'bonus' => 'decimal:2',
            'kpi_percent' => 'decimal:2',
            'delta' => 'decimal:2',
            'salary_accrued' => 'decimal:2',
            'salary_paid' => 'decimal:2',
        ];

        if ($this->hasDeletedAtColumn()) {
            $casts['deleted_at'] = 'datetime';
        }

        if (Schema::hasColumn($this->getTable(), 'accounting_handoff_at')) {
            $casts['accounting_handoff_at'] = 'datetime';
        }

        if (Schema::hasColumn($this->getTable(), 'performers')) {
            $casts['performers'] = 'array';
        }

        if (Schema::hasColumn($this->getTable(), 'wizard_state')) {
            $casts['wizard_state'] = 'array';
        }

        if (Schema::hasColumn($this->getTable(), 'customer_basic_terms')) {
            $casts['customer_basic_terms'] = 'array';
        }

        if (Schema::hasColumn($this->getTable(), 'carrier_basic_terms')) {
            $casts['carrier_basic_terms'] = 'array';
        }

        return $casts;
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return false;
        }

        if (! $this->hasDeletedAtColumn()) {
            return parent::delete();
        }

        if ($this->trashed()) {
            return true;
        }

        return $this->forceFill([
            'deleted_at' => now(),
            'updated_at' => $this->usesTimestamps() ? now() : $this->updated_at,
        ])->saveQuietly();
    }

    public function trashed(): bool
    {
        if (! $this->hasDeletedAtColumn()) {
            return false;
        }

        return $this->getAttribute('deleted_at') !== null;
    }

    public function hasDeletedAtColumn(): bool
    {
        return Schema::hasColumn($this->getTable(), 'deleted_at');
    }

    /**
     * Учитывает колонку заказа, снимок wizard_state и явное значение из запроса мастера.
     */
    public function isInternationalTransportEffective(?bool $requestedOverride = null): bool
    {
        if ($requestedOverride !== null) {
            return $requestedOverride;
        }

        if ((bool) $this->is_international_transport) {
            return true;
        }

        $wizard = is_array($this->wizard_state) ? $this->wizard_state : [];

        return (bool) ($wizard['is_international_transport'] ?? false);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function orderOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function ownCompany(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'own_company_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'carrier_id');
    }

    /**
     * @return HasMany<OrderLeg, $this>
     */
    public function legs(): HasMany
    {
        return $this->hasMany(OrderLeg::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<Cargo, $this>
     */
    public function cargoItems(): HasMany
    {
        return $this->hasMany(Cargo::class)->orderBy('id');
    }

    /**
     * @return MorphMany<ActivityEvent, $this>
     */
    public function activityEvents(): MorphMany
    {
        return $this->morphMany(ActivityEvent::class, 'subject')->orderByDesc('occurred_at');
    }

    /**
     * @return HasMany<DispositionEntry, $this>
     */
    public function dispositionEntries(): HasMany
    {
        return $this->hasMany(DispositionEntry::class);
    }

    /**
     * @return HasMany<OrderDocument, $this>
     */
    public function documents(): HasMany
    {
        $relation = $this->hasMany(OrderDocument::class);

        if (Schema::hasColumn('order_documents', 'document_date')) {
            $relation->orderByDesc('document_date');
        }

        return $relation->orderByDesc('id');
    }

    /**
     * @return HasMany<OrderDocumentEdoAcknowledgement, $this>
     */
    public function edoAcknowledgements(): HasMany
    {
        return $this->hasMany(OrderDocumentEdoAcknowledgement::class);
    }

    /**
     * @return HasMany<FinancialTerm, $this>
     */
    public function financialTerms(): HasMany
    {
        return $this->hasMany(FinancialTerm::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest();
    }

    /**
     * @return HasManyThrough<RoutePoint, OrderLeg, $this>
     */
    public function routePoints(): HasManyThrough
    {
        return $this->hasManyThrough(RoutePoint::class, OrderLeg::class, 'order_id', 'order_leg_id');
    }

    /**
     * @return HasManyThrough<LegContractorAssignment, OrderLeg, $this>
     */
    public function legContractorAssignments(): HasManyThrough
    {
        return $this->hasManyThrough(
            LegContractorAssignment::class,
            OrderLeg::class,
            'order_id',
            'order_leg_id',
            'id',
            'id'
        );
    }

    /**
     * @return HasManyThrough<LegCost, OrderLeg, $this>
     */
    public function legCosts(): HasManyThrough
    {
        return $this->hasManyThrough(
            LegCost::class,
            OrderLeg::class,
            'order_id',
            'order_leg_id',
            'id',
            'id'
        );
    }

    /**
     * Получить всех исполнителей заказа через назначения на плечи
     *
     * @return Collection<int, Contractor>
     */
    public function getAllContractors(): Collection
    {
        return $this->legContractorAssignments()
            ->with('contractor')
            ->get()
            ->pluck('contractor')
            ->filter()
            ->unique('id');
    }

    /**
     * Получить основного исполнителя (первый по sequence)
     */
    public function getPrimaryContractorAttribute(): ?Contractor
    {
        $assignment = $this->legContractorAssignments()
            ->with('contractor')
            ->whereHas('leg', function ($query) {
                $query->orderBy('sequence');
            })
            ->first();

        return $assignment?->contractor;
    }

    /**
     * Получить все ID исполнителей
     *
     * @return array<int>
     */
    public function getAllContractorIdsAttribute(): array
    {
        return $this->legContractorAssignments()
            ->pluck('contractor_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Получить общую стоимость всех плеч
     */
    public function getTotalLegCostsAttribute(): float
    {
        return (float) $this->legCosts()->sum('amount');
    }

    /**
     * Получить статус оплаты по всем плечам
     *
     * @return array<string, mixed>
     */
    public function getLegPaymentStatusAttribute(): array
    {
        $costs = $this->legCosts()->get();

        return [
            'total_amount' => $costs->sum('amount'),
            'paid_amount' => $costs->where('status', 'paid')->sum('amount'),
            'pending_amount' => $costs->where('status', 'confirmed')->sum('amount'),
            'draft_amount' => $costs->where('status', 'draft')->sum('amount'),
            'count' => $costs->count(),
            'paid_count' => $costs->where('status', 'paid')->count(),
        ];
    }
}
