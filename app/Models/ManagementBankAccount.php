<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementBankAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_mask',
        'currency',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ManagementStatementImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(ManagementStatementImport::class, 'bank_account_id');
    }

    /**
     * @return HasMany<ManagementStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ManagementStatementLine::class, 'bank_account_id');
    }

    public static function consolidated(): self
    {
        return self::query()->updateOrCreate(
            ['account_number' => 'CONSOLIDATED'],
            [
                'bank_name' => 'Сводная выписка',
                'account_mask' => 'без счёта',
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 5,
            ],
        );
    }
}
