<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\OrderDocumentEdoAcknowledgementService;
use App\Services\OrderDocumentRequirementService;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrderDocumentsModalController extends Controller
{
    public function __construct(
        private readonly OrderDocumentRequirementService $documentRequirementService,
        private readonly OrderDocumentEdoAcknowledgementService $edoAcknowledgementService,
    ) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->ensureCanViewOrder($request, $order);

        $documents = $order->documents()
            ->orderBy('id')
            ->get()
            ->map(fn (OrderDocument $document): array => $this->serializeListItem($order, $document))
            ->values()
            ->all();

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
            ],
            'documents' => $documents,
            'document_type_options' => $this->documentRequirementService->documentTypeOptions(),
            'requiredDocumentRules' => $this->documentRequirementService->requirementRulesForOrder($order),
            'requiredDocumentChecklist' => $this->documentRequirementService->checklistForOrder($order),
            'edo_acknowledgements' => $this->edoAcknowledgementService->serializeForOrder($order),
            'can_edit_edo_acknowledgements' => RoleAccess::canEditDocumentEdoAcknowledgements($request->user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(Order $order, OrderDocument $document): array
    {
        $metadata = (array) ($document->metadata ?? []);
        $party = (string) ($metadata['party'] ?? 'internal');
        $isPrintWorkflow = $this->isPrintWorkflow($document);
        $previewUrl = $this->previewUrl($order, $document, $isPrintWorkflow);

        return [
            'id' => $document->id,
            'type' => $document->type,
            'type_label' => $this->typeLabel((string) $document->type),
            'party' => $party,
            'party_label' => $this->partyLabel($party),
            'stage' => $metadata['stage'] ?? null,
            'order_leg_stage' => $metadata['order_leg_stage'] ?? null,
            'carrier_contractor_id' => isset($metadata['carrier_contractor_id']) ? (int) $metadata['carrier_contractor_id'] : null,
            'requirement_slot_key' => $metadata['requirement_slot_key'] ?? null,
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
            'status' => $document->status,
            'status_label' => $this->statusLabel((string) $document->status),
            'original_name' => $document->original_name,
            'is_print_workflow' => $isPrintWorkflow,
            'workflow_status' => $isPrintWorkflow && Schema::hasColumn('order_documents', 'workflow_status')
                ? $document->workflow_status
                : null,
            'workflow_status_label' => $isPrintWorkflow && filled($document->workflow_status)
                ? OrderDocumentWorkflowStatus::label((string) $document->workflow_status)
                : null,
            'preview_url' => $previewUrl,
            'wizard_url' => route('orders.edit', $order).'?tab=documents',
            'can_replace' => ! $isPrintWorkflow,
            'can_delete' => true,
        ];
    }

    private function isPrintWorkflow(OrderDocument $document): bool
    {
        if (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template') {
            return true;
        }

        return data_get($document->metadata, 'flow') === 'print_template_workflow';
    }

    private function previewUrl(Order $order, OrderDocument $document, bool $isPrintWorkflow): ?string
    {
        if ($isPrintWorkflow) {
            if (filled($document->file_path) || filled($document->generated_pdf_path)) {
                return route('orders.documents.preview-draft', [$order, $document]);
            }

            return null;
        }

        if (filled($document->file_path)) {
            return route('orders.documents.preview-uploaded', [$order, $document]);
        }

        return null;
    }

    private function typeLabel(string $type): string
    {
        foreach ($this->documentRequirementService->documentTypeOptions() as $option) {
            if ($option['value'] === $type) {
                return $option['label'];
            }
        }

        return $type !== '' ? $type : '—';
    }

    private function partyLabel(string $party): string
    {
        return match ($party) {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            default => 'Внутренний',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Черновик',
            'pending' => 'Ожидает',
            'signed' => 'Подписан',
            'sent' => 'Отправлен',
            default => $status !== '' ? $status : '—',
        };
    }

    private function ensureCanViewOrder(Request $request, Order $order): void
    {
        abort_unless(OrderViewAuthorization::userCanViewOrder($request->user(), $order), 403);
    }
}
