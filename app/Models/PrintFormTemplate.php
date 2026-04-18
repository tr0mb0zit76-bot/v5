<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintFormTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'entity_type',
        'document_type',
        'document_group',
        'party',
        'source_type',
        'contractor_id',
        'is_default',
        'vue_component',
        'pdf_view',
        'requires_internal_signature',
        'requires_counterparty_signature',
        'is_active',
        'version',
        'file_disk',
        'file_path',
        'original_filename',
        'settings',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'requires_internal_signature' => 'boolean',
            'requires_counterparty_signature' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Дополнительные смещения подписи/печати из CRM (маргины в DOCX после вставки картинок).
     * Если false — положение только из макета DOCX (плейсхолдеры), без пост-обработки VML (маргины CRM).
     */
    public function shouldApplyCrmOverlayOffsets(): bool
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        if (! array_key_exists('apply_crm_overlay_offsets', $overlays)) {
            return true;
        }

        return (bool) $overlays['apply_crm_overlay_offsets'];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function entityTypeOptions(): array
    {
        return [
            ['value' => 'order', 'label' => 'Заказ'],
            ['value' => 'lead', 'label' => 'Лид'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function documentTypeOptions(): array
    {
        return [
            ['value' => 'contract_request', 'label' => 'Договор-заявка'],
            ['value' => 'contract', 'label' => 'Договор'],
            ['value' => 'offer', 'label' => 'Коммерческое предложение'],
            ['value' => 'act', 'label' => 'Акт'],
            ['value' => 'other', 'label' => 'Прочее'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function documentGroupOptions(): array
    {
        return [
            ['value' => 'contractual', 'label' => 'Договорные документы'],
            ['value' => 'commercial', 'label' => 'Коммерческие документы'],
            ['value' => 'closing', 'label' => 'Закрывающие документы'],
            ['value' => 'other', 'label' => 'Прочие документы'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function partyOptions(): array
    {
        return [
            ['value' => 'internal', 'label' => 'Внутренняя форма'],
            ['value' => 'customer', 'label' => 'Форма заказчика'],
            ['value' => 'carrier', 'label' => 'Форма перевозчика'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function sourceTypeOptions(): array
    {
        return [
            ['value' => 'system', 'label' => 'Системный шаблон'],
            ['value' => 'external_docx', 'label' => 'DOCX контрагента'],
        ];
    }
}
