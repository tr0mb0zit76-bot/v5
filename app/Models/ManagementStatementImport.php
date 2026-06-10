<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementStatementImport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_account_id',
        'format',
        'file_name',
        'period_from',
        'period_to',
        'imported_by',
        'status',
        'lines_count',
        'lines_allocated',
        'total_in',
        'total_out',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'total_in' => 'decimal:2',
            'total_out' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ManagementBankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ManagementBankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return HasMany<ManagementStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ManagementStatementLine::class, 'import_id');
    }
}
