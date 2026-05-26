<?php

namespace App\Models;

use App\Support\RoleAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
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
        'ai_preferences',
        'ai_learning_enabled',
        'mobile_nav_keys',
        'ui_preferences',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'ai_learning_enabled' => 'boolean',
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
        return $this->hasRole('admin');
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
}
