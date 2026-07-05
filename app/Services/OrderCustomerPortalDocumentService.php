<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderPortalInvite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderCustomerPortalDocumentService
{
    public function __construct(
        private readonly OrderDocumentRequirementService $requirementService,
        private readonly DocumentStorageService $documentStorage,
        private readonly OrderCompensationService $orderCompensationService,
        private readonly OrderPortalInviteAccessService $inviteAccessService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function documentSlotsForInvite(OrderPortalInvite $invite): array
    {
        $order = $invite->relationLoaded('order')
            ? $invite->order
            : Order::query()->with('documents')->findOrFail($invite->order_id);

        $rules = $this->requirementService->rulesForCustomerPortalInvite($order);

        if ($rules === []) {
            return [];
        }

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : $order->documents()->get();

        $checklist = collect($this->requirementService->checklistForOrder($order))->keyBy('key');
        $typeLabels = collect($this->requirementService->documentTypeOptions())->pluck('label', 'value');

        return collect($rules)
            ->map(function (array $rule) use ($documents, $checklist, $typeLabels): array {
                $matching = $this->matchingDocuments($documents, $rule);
                $checklistItem = $checklist->get($rule['key'] ?? '');

                return [
                    'key' => (string) ($rule['key'] ?? ''),
                    'label' => $this->portalSlotLabel($rule),
                    'description' => $this->portalSlotDescription($rule),
                    'slot_kind' => (string) ($rule['slot_kind'] ?? ''),
                    'slot_key' => (string) ($rule['slot_key'] ?? ''),
                    'allows_multiple' => (bool) ($rule['allows_multiple'] ?? false),
                    'completed' => (bool) ($checklistItem['completed'] ?? $matching->isNotEmpty()),
                    'type_options' => collect($rule['accepted_types'] ?? [])
                        ->filter(fn (mixed $type): bool => is_string($type) && $type !== '')
                        ->map(fn (string $type): array => [
                            'value' => $type,
                            'label' => (string) ($typeLabels->get($type) ?? $type),
                        ])
                        ->values()
                        ->all(),
                    'documents' => $matching
                        ->map(fn (OrderDocument $document): array => $this->serializeDocument($document))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(
        OrderPortalInvite $invite,
        array $validated,
        UploadedFile $file,
    ): OrderDocument {
        $order = Order::query()->findOrFail($invite->order_id);
        abort_unless($this->inviteAccessService->canUploadDocuments($order, $invite), 410, 'Ссылка недействительна или перевозка завершена.');

        $rule = $this->resolveRuleForUpload($order, $validated);

        if ($rule === null) {
            throw ValidationException::withMessages([
                'type' => 'Тип документа не подходит для выбранного слота.',
            ]);
        }

        $type = (string) $validated['type'];
        if (! in_array($type, $rule['accepted_types'] ?? [], true)) {
            throw ValidationException::withMessages([
                'type' => 'Недопустимый тип документа для этого слота.',
            ]);
        }

        try {
            $stored = $this->documentStorage->storeOrderUpload($file, $order->id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $metadata = [
            'party' => 'customer',
            'flow' => 'uploaded',
            'storage_driver' => $stored['storage_driver'],
            'requirement_slot_key' => (string) ($rule['slot_key'] ?? $validated['requirement_slot_key']),
            'portal_invite_id' => $invite->id,
            'uploaded_via' => 'customer_portal',
        ];

        $document = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => $type,
            'number' => $this->nullableTrimmedString($validated['number'] ?? null),
            'document_date' => $this->nullableDateString($validated['document_date'] ?? null),
            'original_name' => $stored['original_name'],
            'file_path' => $stored['file_path'],
            'file_size' => $stored['file_size'],
            'mime_type' => $stored['mime_type'],
            'uploaded_by' => null,
            'status' => 'signed',
            'metadata' => $metadata,
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function resolveRuleForUpload(Order $order, array $validated): ?array
    {
        $slotKey = trim((string) ($validated['requirement_slot_key'] ?? ''));
        $slotKind = trim((string) ($validated['slot_kind'] ?? ''));

        return collect($this->requirementService->rulesForCustomerPortalInvite($order))
            ->first(function (array $rule) use ($slotKey, $slotKind): bool {
                if ($slotKey !== '' && (string) ($rule['slot_key'] ?? '') !== $slotKey) {
                    return false;
                }

                if ($slotKind !== '' && (string) ($rule['slot_kind'] ?? '') !== $slotKind) {
                    return false;
                }

                return true;
            });
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @param  array<string, mixed>  $rule
     * @return Collection<int, OrderDocument>
     */
    private function matchingDocuments(Collection $documents, array $rule): Collection
    {
        return $documents
            ->filter(fn (OrderDocument $document): bool => $this->requirementService->documentsMatchingRule($document, $rule))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(OrderDocument $document): array
    {
        $typeLabels = collect($this->requirementService->documentTypeOptions())->pluck('label', 'value');

        return [
            'id' => $document->id,
            'type' => $document->type,
            'type_label' => (string) ($typeLabels->get($document->type) ?? $document->type),
            'original_name' => $document->original_name,
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function portalSlotLabel(array $rule): string
    {
        return match ((string) ($rule['slot_kind'] ?? '')) {
            'customer_request' => 'Заявка заказчика',
            'customer_closing' => 'Закрывающие документы',
            default => (string) ($rule['label'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function portalSlotDescription(array $rule): string
    {
        return match ((string) ($rule['slot_kind'] ?? '')) {
            'customer_request' => 'Загрузите подписанную заявку',
            'customer_closing' => 'Загрузите УПД, счёт-фактуру, акт, пакинг-лист или счёт',
            default => (string) ($rule['description'] ?? ''),
        };
    }
}
