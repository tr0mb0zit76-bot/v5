<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Активные универсальные шаблоны (без привязки к контрагенту) для контекстного меню в реестрах.
     *
     * @return list<array{id:int,name:string,document_type:string}>
     */
    public static function quickDraftMenuOptionsForEntity(string $entityType): array
    {
        if (! Schema::hasTable('print_form_templates')) {
            return [];
        }

        $query = static::query()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->whereNotNull('file_path');

        if (Schema::hasColumn('print_form_templates', 'contractor_id')) {
            $query->whereNull('contractor_id');
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'document_type'])
            ->map(static fn (self $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'document_type' => (string) $row->document_type,
            ])
            ->values()
            ->all();
    }
}
