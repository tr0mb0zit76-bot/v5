<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailMessage extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mail_thread_id',
        'direction',
        'internet_message_id',
        'from_email',
        'to_emails',
        'cc_emails',
        'subject',
        'body_text',
        'body_html',
        'attachments',
        'is_important',
        'retention_summary',
        'content_purged_at',
        'sent_at',
        'lead_offer_id',
        'created_by',
        'mailbox_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'attachments' => 'array',
            'is_important' => 'boolean',
            'content_purged_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function bodyPurged(): bool
    {
        return $this->content_purged_at !== null;
    }

    /**
     * @return BelongsTo<MailThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'mail_thread_id');
    }

    /**
     * @return BelongsTo<LeadOffer, $this>
     */
    public function leadOffer(): BelongsTo
    {
        return $this->belongsTo(LeadOffer::class);
    }
}
