<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'status',
        'source',
        'counterparty_id',
        'responsible_id',
        'title',
        'description',
        'transport_type',
        'loading_location',
        'unloading_location',
        'planned_shipping_date',
        'target_price',
        'target_currency',
        'customer_payment_form',
        'carrier_payment_form',
        'calculated_cost',
        'expected_margin',
        'proposal_sent_at',
        'next_contact_at',
        'lost_reason',
        'close_outcome_primary_flag',
        'close_outcome_secondary_flags',
        'lead_qualification',
        'metadata',
        'performers',
        'precalculation',
        'business_process_id',
        'business_process_stage_id',
        'process_started_at',
        'stage_entered_at',
        'stage_due_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'planned_shipping_date' => 'date',
            'proposal_sent_at' => 'datetime',
            'next_contact_at' => 'datetime',
            'target_price' => 'decimal:2',
            'calculated_cost' => 'decimal:2',
            'expected_margin' => 'decimal:2',
            'lead_qualification' => 'array',
            'close_outcome_secondary_flags' => 'array',
            'metadata' => 'array',
            'performers' => 'array',
            'precalculation' => 'array',
            'process_started_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'stage_due_at' => 'datetime',
        ];

        if ($this->hasDeletedAtColumn()) {
            $casts['deleted_at'] = 'datetime';
        }

        return $casts;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTrashed(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'deleted_at')) {
            return $query->whereNull($this->qualifyColumn('deleted_at'));
        }

        return $query;
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        $query = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value);

        if ($this->hasDeletedAtColumn()) {
            $query->whereNull($this->qualifyColumn('deleted_at'));
        }

        /** @var static|null $lead */
        $lead = $query->first();

        return $lead;
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
     * @return BelongsTo<BusinessProcess, $this>
     */
    public function businessProcess(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class);
    }

    /**
     * @return BelongsTo<BusinessProcessStage, $this>
     */
    public function businessProcessStage(): BelongsTo
    {
        return $this->belongsTo(BusinessProcessStage::class, 'business_process_stage_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'counterparty_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return HasMany<LeadCargoItem, $this>
     */
    public function cargoItems(): HasMany
    {
        return $this->hasMany(LeadCargoItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<LeadRoutePoint, $this>
     */
    public function routePoints(): HasMany
    {
        return $this->hasMany(LeadRoutePoint::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    /**
     * @return HasMany<LeadOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(LeadOffer::class)->latest();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at');
    }

    /**
     * @return MorphMany<ActivityEvent, $this>
     */
    public function activityEvents(): MorphMany
    {
        return $this->morphMany(ActivityEvent::class, 'subject')->orderByDesc('occurred_at');
    }

    /**
     * @return HasMany<LeadAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class)->latest();
    }
}
