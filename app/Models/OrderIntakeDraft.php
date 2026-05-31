<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIntakeDraft extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'source_original_name',
        'source_mime_type',
        'source_storage_path',
        'source_storage_driver',
        'source_text_hash',
        'source_text_length',
        'model',
        'confidence',
        'extracted_payload',
        'wizard_patch',
        'warnings',
        'matched_contractors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'extracted_payload' => 'array',
            'wizard_patch' => 'array',
            'warnings' => 'array',
            'matched_contractors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
