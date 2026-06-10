<?php

namespace App\Models;

use App\Support\MailSync\MailSyncSpamBlocklist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailBlockedSender extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'note',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): mixed => MailSyncSpamBlocklist::forgetCache());
        static::deleted(fn (): mixed => MailSyncSpamBlocklist::forgetCache());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setEmailAttribute(mixed $value): void
    {
        $this->attributes['email'] = is_string($value)
            ? strtolower(trim($value))
            : '';
    }
}
