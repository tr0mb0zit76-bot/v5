<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptTrainerMessage extends Model
{
    protected $fillable = [
        'sales_script_play_session_id',
        'user_id',
        'role',
        'content',
    ];

    /**
     * @return BelongsTo<SalesScriptPlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SalesScriptPlaySession::class, 'sales_script_play_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
