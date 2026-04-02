<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'company_code',
        'manager_id',
        'site_id',
        'order_date',
        'loading_date',
        'unloading_date',
        'customer_rate',
        'customer_payment_form',
        'customer_payment_term',
        'carrier_rate',
        'carrier_payment_form',
        'carrier_payment_term',
        'additional_expenses',
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
        'customer_id',
        'own_company_id',
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
        'performers',
        'payment_terms',
        'special_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
            'ai_metadata' => 'array',
            'ati_response' => 'array',
            'metadata' => 'array',
            'payment_statuses' => 'array',
            'performers' => 'array',
            'customer_rate' => 'decimal:2',
            'carrier_rate' => 'decimal:2',
            'additional_expenses' => 'decimal:2',
            'insurance' => 'decimal:2',
            'bonus' => 'decimal:2',
            'kpi_percent' => 'decimal:2',
            'delta' => 'decimal:2',
            'salary_accrued' => 'decimal:2',
            'salary_paid' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
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
     * @return HasMany<OrderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class)->orderByDesc('document_date')->orderByDesc('id');
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
}
