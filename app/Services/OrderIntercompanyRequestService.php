<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Models\User;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderIntercompanySubcontract;
use App\Support\OrderPrintFormContext;
use Illuminate\Validation\ValidationException;

/**
 * Межфирменная заявка: DOCX + подпись по кнопке «Создать реализацию».
 */
class OrderIntercompanyRequestService
{
    public function __construct(
        private readonly OrderPrintDocumentWorkflowService $workflow,
    ) {}

    public function ensureSigned(Order $order, User $user): ?OrderDocument
    {
        if (! OrderIntercompanySubcontract::applies($order)) {
            return null;
        }

        $amount = OrderIntercompanySubcontract::amount($order);
        if ($amount === null) {
            throw ValidationException::withMessages([
                'one_c' => 'Не удалось рассчитать сумму межфирменной заявки (customer_rate × 0.95).',
            ]);
        }

        $existing = $this->findActiveSigned($order);
        if ($existing !== null && $this->amountMatches($existing, $amount)) {
            return $existing;
        }

        $template = $this->resolveRequestTemplate();
        if ($template === null) {
            throw ValidationException::withMessages([
                'one_c' => 'Нет активного шаблона заявки для межфирменного субподряда.',
            ]);
        }

        $context = OrderPrintFormContext::forIntercompanySubcontract();
        $document = $this->workflow->createFromTemplate($order, $template, $user, $context);

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata['intercompany_amount'] = $amount;
        $metadata['intercompany_subcontract'] = true;
        $metadata['requirement_slot_key'] = OrderIntercompanySubcontract::SLOT_KEY;
        $metadata['party'] = 'internal';
        $document->update(['metadata' => $metadata]);

        $this->workflow->requestApproval($document->refresh(), $user);
        $this->workflow->approve($document->refresh(), $user);

        $document->refresh()->update([
            'status' => 'signed',
            'signature_status' => 'signed_internal',
            'internal_signed_at' => now(),
            'internal_signed_by' => $user->id,
        ]);

        return $document->refresh();
    }

    private function findActiveSigned(Order $order): ?OrderDocument
    {
        return OrderDocument::query()
            ->where('order_id', $order->id)
            ->whereIn('workflow_status', [
                OrderDocumentWorkflowStatus::APPROVED,
                OrderDocumentWorkflowStatus::FINALIZED,
            ])
            ->where('metadata->intercompany_subcontract', true)
            ->orderByDesc('id')
            ->first();
    }

    private function amountMatches(OrderDocument $document, string $amount): bool
    {
        $stored = (string) data_get($document->metadata, 'intercompany_amount', '');

        return $stored !== '' && bccomp($stored, $amount, 2) === 0;
    }

    private function resolveRequestTemplate(): ?PrintFormTemplate
    {
        return PrintFormTemplate::query()
            ->where('entity_type', 'order')
            ->where('is_active', true)
            ->whereIn('document_type', ['request', 'contract_request'])
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->orderBy('id')
            ->first();
    }
}
