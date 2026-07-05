<?php

namespace App\Models;

use App\Support\ExternalParty;
use App\Support\RoleAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'theme',
        'is_active',
        'has_signing_authority',
        'belongs_to_management',
        'can_management_accounting',
        'sees_company_dashboard',
        'ai_preferences',
        'ai_learning_enabled',
        'mobile_nav_keys',
        'ui_preferences',
        'mail_sync_enabled',
        'is_external',
        'contractor_id',
        'contractor_contact_id',
        'external_party',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mail_imap_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'has_signing_authority' => 'boolean',
            'belongs_to_management' => 'boolean',
            'can_management_accounting' => 'boolean',
            'sees_company_dashboard' => 'boolean',
            'ai_learning_enabled' => 'boolean',
            'mail_imap_secret' => 'encrypted',
            'mail_sync_enabled' => 'boolean',
            'is_external' => 'boolean',
            'mail_last_sync_at' => 'datetime',
            'ai_preferences' => 'array',
            'mobile_nav_keys' => 'array',
            'ui_preferences' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        return RoleAccess::userHasRoleName($this, $roleName);
    }

    public function isAdmin(): bool
    {
        return RoleAccess::isAdminUser($this);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function hasSigningAuthority(): bool
    {
        return (bool) $this->has_signing_authority;
    }

    public function belongsToManagement(): bool
    {
        return (bool) $this->belongs_to_management;
    }

    public function canManagementAccounting(): bool
    {
        return (bool) $this->can_management_accounting;
    }

    public function seesCompanyDashboard(): bool
    {
        return (bool) ($this->sees_company_dashboard ?? false);
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot(['is_primary', 'receives_approvals'])
            ->withTimestamps();
    }

    public function primaryDepartmentId(): ?int
    {
        if (! Schema::hasTable('department_user')) {
            return null;
        }

        if ($this->relationLoaded('departments')) {
            $primary = $this->departments->first(fn (Department $department): bool => (bool) $department->pivot->is_primary);

            if ($primary !== null) {
                return (int) $primary->id;
            }

            $first = $this->departments->first();

            return $first !== null ? (int) $first->id : null;
        }

        $primaryDepartmentId = $this->departments()
            ->wherePivot('is_primary', true)
            ->value('departments.id');

        if ($primaryDepartmentId !== null) {
            return (int) $primaryDepartmentId;
        }

        $fallbackDepartmentId = $this->departments()->value('departments.id');

        return $fallbackDepartmentId !== null ? (int) $fallbackDepartmentId : null;
    }

    /**
     * @return list<int>
     */
    public function approvalDepartmentIds(): array
    {
        if (! Schema::hasTable('department_user')) {
            return [];
        }

        if ($this->relationLoaded('departments')) {
            return $this->departments
                ->filter(fn (Department $department): bool => (bool) $department->pivot->receives_approvals)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return $this->departments()
            ->wherePivot('receives_approvals', true)
            ->pluck('departments.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return BelongsToMany<Contractor, $this>
     */
    public function signingOwnCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Contractor::class, 'user_signing_own_company')
            ->withTimestamps();
    }

    /**
     * Пустой список ограничений — подпись по всем «нашим» компаниям.
     */
    public function signingOwnCompaniesUnrestricted(): bool
    {
        if (! $this->hasSigningAuthority()) {
            return false;
        }

        if (! Schema::hasTable('user_signing_own_company')) {
            return true;
        }

        if ($this->relationLoaded('signingOwnCompanies')) {
            return $this->signingOwnCompanies->isEmpty();
        }

        return ! $this->signingOwnCompanies()->exists();
    }

    /**
     * @return list<int>
     */
    public function signingOwnCompanyIds(): array
    {
        if (! Schema::hasTable('user_signing_own_company')) {
            return [];
        }

        if ($this->relationLoaded('signingOwnCompanies')) {
            return $this->signingOwnCompanies
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return $this->signingOwnCompanies()
            ->pluck('contractors.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function canSignDocumentsForOwnCompany(?int $ownCompanyId): bool
    {
        if (! $this->hasSigningAuthority()) {
            return false;
        }

        if ($this->signingOwnCompaniesUnrestricted()) {
            return true;
        }

        if ($ownCompanyId === null || $ownCompanyId <= 0) {
            return true;
        }

        return in_array($ownCompanyId, $this->signingOwnCompanyIds(), true);
    }

    public function hasMailImapCredential(): bool
    {
        return filled($this->getRawOriginal('mail_imap_secret'));
    }

    public function applyMailImapPassword(?string $plain): void
    {
        if (! is_string($plain) || $plain === '') {
            return;
        }

        $this->forceFill(['mail_imap_secret' => $plain]);
    }

    public function isExternal(): bool
    {
        return (bool) ($this->is_external ?? false);
    }

    public function externalParty(): ?ExternalParty
    {
        $raw = $this->external_party;

        return is_string($raw) && $raw !== '' ? ExternalParty::tryFrom($raw) : null;
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<ContractorContact, $this>
     */
    public function contractorContact(): BelongsTo
    {
        return $this->belongsTo(ContractorContact::class);
    }
}
