<?php

namespace App\Services\ExternalUsers;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentRequirementService;
use App\Support\CounterpartyOrderAccess;
use App\Support\ExternalParty;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CounterpartyOrderDocumentService
{
    public function __construct(
        private readonly CounterpartyOrderAccess $orderAccess,
        private readonly OrderDocumentRequirementService $requirementService,
        private readonly DocumentStorageService $documentStorage,
        private readonly OrderCompensationService $orderCompensationService,
    ) {}

    /**
     * @return array{order: array<string, mixed>, slots: list<array<string, mixed>>}
     */
    public function documentSlotsForUser(User $user, Order $order): array
    {
        abort_unless($user->isExternal(), 403);
        abort_unless($this->orderAccess->userCanViewOrder($user, $order), 403);

        $party = $user->externalParty()?->value;
        $contractorId = (int) $user->contractor_id;

        $order->loadMissing(['client:id,name']);
        $rules = collect($this->requirementService->requirementRulesForOrder($order))
            ->filter(function (array $rule) use ($party, $contractorId): bool {
                if (($rule['party'] ?? null) !== $party) {
                    return false;
                }

                if ($party === ExternalParty::Carrier->value) {
                    $ruleContractorId = isset($rule['contractor_id']) ? (int) $rule['contractor_id'] : null;

                    return $ruleContractorId === null || $ruleContractorId === $contractorId;
                }

                return true;
            });

        $checklist = collect($this->requirementService->checklistForOrder($order))->keyBy('key');
        $slots = [];

        foreach ($rules as $rule) {
            $key = (string) ($rule['key'] ?? '');
            $acceptedTypes = $rule['accepted_types'] ?? ['other'];

            $slots[] = [
                'key' => $key,
                'label' => (string) ($rule['label'] ?? $key),
                'party' => (string) ($rule['party'] ?? $party),
                'type' => (string) ($acceptedTypes[0] ?? 'other'),
                'requirement_slot_key' => (string) ($rule['slot_key'] ?? $key),
                'order_leg_stage' => $rule['order_leg_stage'] ?? null,
                'contractor_id' => isset($rule['contractor_id']) ? (int) $rule['contractor_id'] : null,
                'completed' => (bool) ($checklist->get($key)['completed'] ?? false),
            ];
        }

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'customer_name' => $order->client?->name,
            ],
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, Order $order, array $validated, UploadedFile $file): OrderDocument
    {
        abort_unless($user->isExternal(), 403);
        abort_unless($this->orderAccess->userCanViewOrder($user, $order), 403);

        $party = $user->externalParty()?->value ?? 'carrier';
        $slotKey = trim((string) ($validated['requirement_slot_key'] ?? ''));

        $rule = collect($this->requirementService->requirementRulesForOrder($order))
            ->first(function (array $rule) use ($party, $user, $slotKey): bool {
                if (($rule['party'] ?? null) !== $party) {
                    return false;
                }

                if ($party === ExternalParty::Carrier->value) {
                    $ruleContractorId = isset($rule['contractor_id']) ? (int) $rule['contractor_id'] : null;

                    if ($ruleContractorId !== null && $ruleContractorId !== (int) $user->contractor_id) {
                        return false;
                    }
                }

                return $slotKey === '' || (string) ($rule['slot_key'] ?? $rule['key'] ?? '') === $slotKey;
            });

        if ($rule === null) {
            throw ValidationException::withMessages([
                'requirement_slot_key' => 'Слот документа недоступен для вашей стороны.',
            ]);
        }

        $type = (string) ($validated['type'] ?? ($rule['accepted_types'][0] ?? 'other'));
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
            'party' => $party,
            'flow' => 'uploaded',
            'storage_driver' => $stored['storage_driver'],
            'requirement_slot_key' => (string) ($rule['slot_key'] ?? $slotKey),
            'uploaded_via' => 'traklo_counterparty',
            'external_user_id' => $user->id,
        ];

        if ($party === ExternalParty::Carrier->value) {
            $metadata['carrier_contractor_id'] = (int) $user->contractor_id;
        }

        if (filled($rule['order_leg_stage'] ?? null)) {
            $metadata['order_leg_stage'] = (string) $rule['order_leg_stage'];
        }

        $document = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => $type,
            'number' => filled($validated['number'] ?? null) ? trim((string) $validated['number']) : null,
            'document_date' => filled($validated['document_date'] ?? null) ? (string) $validated['document_date'] : null,
            'original_name' => $stored['original_name'],
            'file_path' => $stored['file_path'],
            'file_size' => $stored['file_size'],
            'mime_type' => $stored['mime_type'],
            'uploaded_by' => $user->id,
            'status' => 'signed',
            'metadata' => $metadata,
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return $document;
    }
}
