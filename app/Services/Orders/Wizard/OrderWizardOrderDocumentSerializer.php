<?php

namespace App\Services\Orders\Wizard;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderWizardOrderDocumentSerializer
{
    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     * @return array<string, mixed>
     */
    public function serialize(
        OrderDocument $document,
        Order $order,
        bool $canManage,
        bool $canApprove,
        Collection $templatesById,
    ): array {
        $base = [
            'id' => $document->id,
            'type' => $document->type,
            'flow' => data_get($document->metadata, 'flow', 'uploaded'),
            'party' => $this->resolveWizardDocumentParty($document, $templatesById),
            'stage' => data_get($document->metadata, 'stage'),
            'order_leg_stage' => data_get($document->metadata, 'order_leg_stage')
                ?? data_get($document->metadata, 'stage'),
            'carrier_contractor_id' => data_get($document->metadata, 'carrier_contractor_id'),
            'carrier_slot' => data_get($document->metadata, 'carrier_slot'),
            'contractor_id' => data_get($document->metadata, 'contractor_id'),
            'requirement_slot_key' => data_get($document->metadata, 'requirement_slot_key'),
            'route_legs_as_table_rows' => (bool) data_get($document->metadata, 'route_legs_as_table_rows', false),
            'requirement_key' => data_get($document->metadata, 'requirement_key'),
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
            'status' => $document->status,
            'original_name' => $document->original_name,
            'file_path' => $document->file_path,
            'generated_pdf_path' => $document->generated_pdf_path,
            'template_id' => $document->template_id,
            'is_print_workflow' => false,
        ];

        $isPrintWorkflow = (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || (data_get($document->metadata, 'flow') === 'print_template_workflow');

        if (! $isPrintWorkflow) {
            $uploadedPreviewUrl = filled($document->file_path)
                ? route('orders.documents.preview-uploaded', [$order, $document])
                : null;

            return array_merge($base, [
                'uploaded_file_preview_url' => $uploadedPreviewUrl,
            ]);
        }

        $workflowStatus = Schema::hasColumn('order_documents', 'workflow_status')
            ? $document->workflow_status
            : null;

        $requiresCounterpartySignature = $this->orderDocumentRequiresCounterpartySignature($document);

        $signatureStatus = Schema::hasColumn('order_documents', 'signature_status')
            ? $document->signature_status
            : null;

        $draftUrl = filled($document->file_path)
            ? route('orders.documents.download-draft', [$order, $document])
            : null;

        $draftPreviewUrl = $draftUrl !== null
            ? route('orders.documents.preview-draft', [$order, $document])
            : null;

        if (in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true) && blank($document->generated_pdf_path)) {
            app(OrderPrintDocumentWorkflowService::class)->ensureApprovedWorkflowPdf($document);
            $document->refresh();
        }

        $finalUrl = filled($document->generated_pdf_path)
            ? route('orders.documents.download-final', [$order, $document])
            : null;

        $isWorkflowSigned = in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true);

        $printPartyLabel = null;
        $printTemplateName = $this->printTemplateName($document, $templatesById);
        $printTemplateCode = $this->printTemplateCode($document, $templatesById);
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $tpl */
            $tpl = $templatesById->get($document->template_id);
            $printPartyLabel = $this->printTemplatePartyLabel($tpl);
        }

        return array_merge($base, [
            'is_print_workflow' => true,
            'source' => Schema::hasColumn('order_documents', 'source') ? $document->source : null,
            'workflow_status' => $workflowStatus,
            'workflow_status_label' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
            'print_party_label' => $printPartyLabel,
            'print_template_name' => $printTemplateName,
            'print_template_code' => $printTemplateCode,
            'approval_requested_at' => Schema::hasColumn('order_documents', 'approval_requested_at')
                ? optional($document->approval_requested_at)?->toIso8601String()
                : null,
            'approved_at' => Schema::hasColumn('order_documents', 'approved_at')
                ? optional($document->approved_at)?->toIso8601String()
                : null,
            'rejected_at' => Schema::hasColumn('order_documents', 'rejected_at')
                ? optional($document->rejected_at)?->toIso8601String()
                : null,
            'rejection_reason' => Schema::hasColumn('order_documents', 'rejection_reason')
                ? $document->rejection_reason
                : null,
            'draft_download_url' => $isWorkflowSigned ? null : $draftUrl,
            'draft_preview_url' => $isWorkflowSigned ? null : $draftPreviewUrl,
            'final_pdf_download_url' => $finalUrl,
            'final_pdf_storage_path' => filled($document->generated_pdf_path) ? $document->generated_pdf_path : null,
            'draft_storage_path' => filled($document->file_path) ? $document->file_path : null,
            'can_request_approval' => $canManage && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true),
            'can_regenerate_draft' => $canManage && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true),
            'can_approve' => $canApprove && $workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'can_reject' => $canApprove && $workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'can_finalize' => $canManage && $workflowStatus === OrderDocumentWorkflowStatus::APPROVED,
            'can_discard_print_draft' => $this->canDiscardPrintWorkflowDocument(
                $document,
                $workflowStatus,
                $canManage,
                $canApprove
            ),
            'requires_counterparty_signature' => $requiresCounterpartySignature,
            'signature_status' => $signatureStatus,
            'signature_status_label' => $this->orderDocumentSignatureStatusLabel($signatureStatus),
            'signature_followup_hint' => $this->orderDocumentSignatureFollowupHint(
                $workflowStatus,
                $signatureStatus,
                $requiresCounterpartySignature
            ),
        ]);
    }

    public function isEmptyPrintWorkflowArtifact(OrderDocument $document): bool
    {
        $isPrintWorkflow = (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || data_get($document->metadata, 'flow') === 'print_template_workflow';

        return $isPrintWorkflow
            && blank($document->file_path)
            && blank($document->generated_pdf_path)
            && blank($document->original_name);
    }

    private function orderDocumentRequiresCounterpartySignature(OrderDocument $document): bool
    {
        if (Schema::hasColumn('order_documents', 'requires_counterparty_signature')) {
            return (bool) ($document->requires_counterparty_signature ?? false);
        }

        if ($document->template_id === null) {
            return false;
        }

        $template = PrintFormTemplate::query()->find($document->template_id);

        return (bool) ($template?->requires_counterparty_signature ?? false);
    }

    private function canDiscardPrintWorkflowDocument(
        OrderDocument $document,
        ?string $workflowStatus,
        bool $canManageOrderDocuments,
        bool $canApproveOrderDocuments,
    ): bool {
        if ($workflowStatus === OrderDocumentWorkflowStatus::FINALIZED) {
            return false;
        }

        $signatureStatus = Schema::hasColumn('order_documents', 'signature_status')
            ? (string) ($document->signature_status ?? '')
            : '';

        if ($signatureStatus === 'signed_both_sides') {
            return false;
        }

        $managerMayDiscardBeforeApproval = $canManageOrderDocuments
            && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true);

        $signerMayDiscardUntilBothSignatures = $canApproveOrderDocuments
            && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::PENDING_APPROVAL,
                OrderDocumentWorkflowStatus::APPROVED,
            ], true);

        return $managerMayDiscardBeforeApproval || $signerMayDiscardUntilBothSignatures;
    }

    private function printTemplatePartyLabel(PrintFormTemplate $template): string
    {
        return match ((string) $template->party) {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            'internal' => 'Внутренняя',
            default => (string) $template->party,
        };
    }

    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     */
    private function printTemplateName(OrderDocument $document, Collection $templatesById): ?string
    {
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);

            return $template->name;
        }

        $name = data_get($document->metadata, 'template_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     */
    private function printTemplateCode(OrderDocument $document, Collection $templatesById): ?string
    {
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);

            return $template->code;
        }

        $code = data_get($document->metadata, 'template_code');

        return is_string($code) && trim($code) !== '' ? trim($code) : null;
    }

    /**
     * Подпись в смысле «документ подписан сторонами», не путать с workflow_status печатной заявки.
     */
    private function orderDocumentSignatureStatusLabel(?string $signatureStatus): ?string
    {
        if ($signatureStatus === null || $signatureStatus === '') {
            return null;
        }

        return match ($signatureStatus) {
            'not_requested' => 'Подпись не зафиксирована',
            'pending_signature' => 'Ожидается подпись',
            'signed_internal' => 'Подписано у нас (внутренняя)',
            'signed_both_sides' => 'Подписано с обеих сторон',
            default => $signatureStatus,
        };
    }

    private function orderDocumentSignatureFollowupHint(
        ?string $workflowStatus,
        ?string $signatureStatus,
        bool $requiresCounterpartySignature,
    ): ?string {
        if (! $requiresCounterpartySignature) {
            return null;
        }

        if ($workflowStatus !== OrderDocumentWorkflowStatus::FINALIZED) {
            return null;
        }

        if ($signatureStatus === 'signed_both_sides') {
            return null;
        }

        if ($signatureStatus === 'signed_internal') {
            return 'Нужна подпись клиента: приложите скан (или отдельный файл в блоке «Документы заказчика» ниже).';
        }

        return null;
    }

    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     */
    private function resolveWizardDocumentParty(OrderDocument $document, Collection $templatesById): string
    {
        $party = (string) data_get($document->metadata, 'party', 'internal');

        if (in_array($party, ['customer', 'carrier'], true)) {
            return $party;
        }

        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);
            $templateParty = (string) ($template->party ?? '');

            if (in_array($templateParty, ['customer', 'carrier'], true)) {
                return $templateParty;
            }
        }

        if (data_get($document->metadata, 'carrier_contractor_id')) {
            return 'carrier';
        }

        $code = strtolower((string) data_get($document->metadata, 'template_code', ''));

        if (str_contains($code, 'perevoz') || str_contains($code, 'carrier') || str_contains($code, 'перевоз')) {
            return 'carrier';
        }

        if (str_contains($code, 'zak') || str_contains($code, 'kl') || str_contains($code, 'client') || str_contains($code, 'зак')) {
            return 'customer';
        }

        if (in_array($document->type, ['request', 'contract_request'], true)) {
            return 'customer';
        }

        return 'internal';
    }
}
