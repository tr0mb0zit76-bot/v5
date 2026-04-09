<?php

namespace App\Models;

use Database\Factories\OrderDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDocument extends Model
{
    /** @use HasFactory<OrderDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'type',
        'document_group',
        'source',
        'number',
        'document_date',
        'original_name',
        'file_path',
        'generated_pdf_path',
        'template_id',
        'status',
        'workflow_status',
        'requires_counterparty_signature',
        'signature_status',
        'signed_at',
        'signed_by',
        'file_size',
        'mime_type',
        'uploaded_by',
        'metadata',
        'approval_requested_at',
        'approval_requested_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'internal_signed_at',
        'internal_signed_by',
        'internal_signed_file_path',
        'counterparty_signed_at',
        'counterparty_signed_file_path',
        'snapshot_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'signed_at' => 'datetime',
            'metadata' => 'array',
            'snapshot_payload' => 'array',
            'approval_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'internal_signed_at' => 'datetime',
            'counterparty_signed_at' => 'datetime',
            'requires_counterparty_signature' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PrintFormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintFormTemplate::class, 'template_id');
    }
}
