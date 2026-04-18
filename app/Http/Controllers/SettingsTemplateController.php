<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrintFormTemplateRequest;
use App\Http\Requests\UpdatePrintFormTemplateRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Services\DocxPlaceholderExtractor;
use App\Services\LeadPrintFormDraftService;
use App\Services\OrderPrintFormDraftService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Services\PrintFormVariableCatalog;
use App\Support\DocumentPreview;
use App\Support\PrintFormPlaceholderPathResolver;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsTemplateController extends Controller
{
    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
        private readonly PrintFormVariableCatalog $variableCatalog,
        private readonly OrderPrintFormDraftService $orderDraftService,
        private readonly LeadPrintFormDraftService $leadDraftService,
        private readonly PrintFormDraftResponseBuilder $draftResponseBuilder,
        private readonly PrintFormPlaceholderPathResolver $placeholderPathResolver,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $templates = collect();

        if (Schema::hasTable('print_form_templates')) {
            $templates = PrintFormTemplate::query()
                ->when(
                    Schema::hasColumn('print_form_templates', 'contractor_id'),
                    fn ($query) => $query->with(['contractor:id,name'])
                )
                ->orderByDesc('is_default')
                ->orderBy('document_type')
                ->orderBy('name')
                ->get()
                ->map(fn (PrintFormTemplate $template): array => [
                    'id' => $template->id,
                    'code' => $template->code,
                    'name' => $template->name,
                    'entity_type' => $template->entity_type ?? 'order',
                    'document_type' => $template->document_type,
                    'document_group' => $template->document_group,
                    'party' => $template->party,
                    'source_type' => $template->source_type ?? 'system',
                    'contractor_id' => $template->contractor_id,
                    'contractor_name' => $template->contractor?->name,
                    'is_default' => (bool) $template->is_default,
                    'requires_internal_signature' => (bool) $template->requires_internal_signature,
                    'requires_counterparty_signature' => (bool) $template->requires_counterparty_signature,
                    'is_active' => (bool) $template->is_active,
                    'version' => (int) $template->version,
                    'original_filename' => $template->original_filename,
                    'has_source_file' => filled($template->file_path),
                    'pipeline_status' => data_get($template->settings, 'pipeline_status', 'draft'),
                    'variables' => data_get($template->settings, 'variables', []),
                    'variable_mapping' => $this->placeholderPathResolver->effectiveVariableMapping(
                        is_array(data_get($template->settings, 'variables')) ? data_get($template->settings, 'variables') : [],
                        is_array(data_get($template->settings, 'variable_mapping')) ? data_get($template->settings, 'variable_mapping') : [],
                        (string) ($template->entity_type ?? 'order'),
                    ),
                    'internal_signature_placeholder' => data_get($template->settings, 'image_overlays.internal_signature.placeholder', 'internal_signature_image'),
                    'internal_stamp_placeholder' => data_get($template->settings, 'image_overlays.internal_stamp.placeholder', 'internal_stamp_image'),
                    'signature_image_width_mm' => (float) data_get($template->settings, 'image_overlays.internal_signature.width_mm', 42),
                    'signature_image_height_mm' => (float) data_get($template->settings, 'image_overlays.internal_signature.height_mm', 18),
                    'signature_image_offset_x_mm' => (float) data_get($template->settings, 'image_overlays.internal_signature.offset_x_mm', 0),
                    'signature_image_offset_y_mm' => (float) data_get($template->settings, 'image_overlays.internal_signature.offset_y_mm', 0),
                    'stamp_image_width_mm' => (float) data_get($template->settings, 'image_overlays.internal_stamp.width_mm', 30),
                    'stamp_image_height_mm' => (float) data_get($template->settings, 'image_overlays.internal_stamp.height_mm', 30),
                    'stamp_image_offset_x_mm' => (float) data_get($template->settings, 'image_overlays.internal_stamp.offset_x_mm', 0),
                    'stamp_image_offset_y_mm' => (float) data_get($template->settings, 'image_overlays.internal_stamp.offset_y_mm', 0),
                    'apply_crm_overlay_offsets' => (bool) data_get($template->settings, 'image_overlays.apply_crm_overlay_offsets', true),
                    'has_signature_image' => filled(data_get($template->settings, 'image_overlays.internal_signature.path')),
                    'has_stamp_image' => filled(data_get($template->settings, 'image_overlays.internal_stamp.path')),
                    'signature_image_preview_url' => filled(data_get($template->settings, 'image_overlays.internal_signature.path'))
                        ? route('settings.templates.overlay-asset', ['printFormTemplate' => $template->id, 'overlayKey' => 'internal_signature'])
                        : null,
                    'stamp_image_preview_url' => filled(data_get($template->settings, 'image_overlays.internal_stamp.path'))
                        ? route('settings.templates.overlay-asset', ['printFormTemplate' => $template->id, 'overlayKey' => 'internal_stamp'])
                        : null,
                    'updated_at' => $template->updated_at?->toIso8601String(),
                ])
                ->values();
        }

        $contractorOptions = collect();

        if (Schema::hasTable('contractors')) {
            $contractorOptions = Contractor::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Contractor $contractor): array => [
                    'id' => $contractor->id,
                    'name' => $contractor->name,
                ])
                ->values();
        }

        return Inertia::render('Settings/Templates', [
            'templates' => $templates,
            'contractorOptions' => $contractorOptions,
            'entityTypeOptions' => PrintFormTemplate::entityTypeOptions(),
            'documentTypeOptions' => PrintFormTemplate::documentTypeOptions(),
            'documentGroupOptions' => PrintFormTemplate::documentGroupOptions(),
            'partyOptions' => PrintFormTemplate::partyOptions(),
            'sourceTypeOptions' => PrintFormTemplate::sourceTypeOptions(),
            'orderVariableOptions' => $this->variableCatalog->orderOptions(),
            'leadVariableOptions' => $this->variableCatalog->leadOptions(),
            'documentPreview' => DocumentPreview::inertiaMeta(),
        ]);
    }

    public function store(StorePrintFormTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $template = PrintFormTemplate::query()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'document_type' => $validated['document_type'],
            'document_group' => $validated['document_group'],
            'party' => $validated['party'],
            'source_type' => $validated['source_type'],
            'contractor_id' => $validated['contractor_id'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'requires_internal_signature' => (bool) ($validated['requires_internal_signature'] ?? true),
            'requires_counterparty_signature' => (bool) ($validated['requires_counterparty_signature'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'version' => 1,
            'vue_component' => $validated['source_type'] === 'system' ? 'SystemPrintFormTemplate' : 'ExternalDocxTemplate',
            'pdf_view' => null,
            'settings' => [
                'variables' => [],
                'variable_mapping' => $this->normalizeVariableMappings($validated['variable_mappings'] ?? []),
                'image_overlays' => $this->normalizeImageOverlaySettings($validated),
                'pipeline_status' => 'uploaded',
            ],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->persistSourceFile($template, $request->file('source_file'));
        $this->persistImageOverlayFiles(
            $template,
            $request->file('signature_image_file'),
            $request->file('stamp_image_file')
        );

        return to_route('settings.templates.index');
    }

    public function update(UpdatePrintFormTemplateRequest $request, PrintFormTemplate $printFormTemplate): RedirectResponse
    {
        $validated = $request->validated();

        $printFormTemplate->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'document_type' => $validated['document_type'],
            'document_group' => $validated['document_group'],
            'party' => $validated['party'],
            'source_type' => $validated['source_type'],
            'contractor_id' => $validated['contractor_id'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'requires_internal_signature' => (bool) ($validated['requires_internal_signature'] ?? true),
            'requires_counterparty_signature' => (bool) ($validated['requires_counterparty_signature'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'vue_component' => $validated['source_type'] === 'system' ? 'SystemPrintFormTemplate' : 'ExternalDocxTemplate',
            'updated_by' => $request->user()?->id,
        ]);

        $this->syncVariableMappings($printFormTemplate, $validated['variable_mappings'] ?? []);
        $this->syncImageOverlaySettings($printFormTemplate, $validated);

        $this->persistSourceFile($printFormTemplate, $request->file('source_file'));
        $this->persistImageOverlayFiles(
            $printFormTemplate,
            $request->file('signature_image_file'),
            $request->file('stamp_image_file')
        );

        return to_route('settings.templates.index');
    }

    public function generateOrderDraft(Request $request, PrintFormTemplate $printFormTemplate): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_if($printFormTemplate->entity_type !== 'order', 422, 'Черновик можно сформировать только для шаблона заказа.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $printFormTemplate->refresh();

        $order = Order::query()->findOrFail($validated['order_id']);
        $generatedFile = $this->orderDraftService->generate(
            $printFormTemplate,
            $order,
            ! $request->boolean('exclude_overlays')
        );

        return $this->draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    public function generateLeadDraft(Request $request, PrintFormTemplate $printFormTemplate): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_if($printFormTemplate->entity_type !== 'lead', 422, 'Черновик можно сформировать только для шаблона лида.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');

        $validated = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ]);

        $printFormTemplate->refresh();

        $lead = Lead::query()->findOrFail($validated['lead_id']);
        $generatedFile = $this->leadDraftService->generate(
            $printFormTemplate,
            $lead,
            ! $request->boolean('exclude_overlays')
        );

        return $this->draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    public function previewOrderOverlay(Request $request, PrintFormTemplate $printFormTemplate): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        abort_if($printFormTemplate->entity_type !== 'order', 422, 'Этот режим доступен только для шаблонов заказа.');

        $excludeOverlays = $printFormTemplate->shouldApplyCrmOverlayOffsets() ? 1 : 0;

        return Inertia::render('Settings/TemplateOverlayPreview', $this->buildOverlayPreviewPayload(
            $printFormTemplate,
            route('settings.templates.generate-order-draft', [
                'printFormTemplate' => $printFormTemplate->id,
                'order_id' => (int) $validated['order_id'],
                'preview' => 1,
                'preview_mode' => 'browser',
                'exclude_overlays' => $excludeOverlays,
                'cb' => $this->draftPreviewCacheBuster($printFormTemplate),
            ]),
            ['order_id' => (int) $validated['order_id']]
        ));
    }

    public function previewLeadOverlay(Request $request, PrintFormTemplate $printFormTemplate): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ]);

        abort_if($printFormTemplate->entity_type !== 'lead', 422, 'Этот режим доступен только для шаблонов лида.');

        $excludeOverlays = $printFormTemplate->shouldApplyCrmOverlayOffsets() ? 1 : 0;

        return Inertia::render('Settings/TemplateOverlayPreview', $this->buildOverlayPreviewPayload(
            $printFormTemplate,
            route('settings.templates.generate-lead-draft', [
                'printFormTemplate' => $printFormTemplate->id,
                'lead_id' => (int) $validated['lead_id'],
                'preview' => 1,
                'preview_mode' => 'browser',
                'exclude_overlays' => $excludeOverlays,
                'cb' => $this->draftPreviewCacheBuster($printFormTemplate),
            ]),
            ['lead_id' => (int) $validated['lead_id']]
        ));
    }

    private function draftPreviewCacheBuster(PrintFormTemplate $template): int
    {
        return (int) ($template->updated_at?->getTimestamp() ?? time());
    }

    public function updateOverlayPositions(Request $request, PrintFormTemplate $printFormTemplate): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'signature_offset_x_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'signature_offset_y_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'stamp_offset_x_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'stamp_offset_y_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ]);

        $settings = is_array($printFormTemplate->settings) ? $printFormTemplate->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];
        $signature = is_array($overlays['internal_signature'] ?? null) ? $overlays['internal_signature'] : [];
        $stamp = is_array($overlays['internal_stamp'] ?? null) ? $overlays['internal_stamp'] : [];

        $signature['offset_x_mm'] = (float) $validated['signature_offset_x_mm'];
        $signature['offset_y_mm'] = (float) $validated['signature_offset_y_mm'];
        $stamp['offset_x_mm'] = (float) $validated['stamp_offset_x_mm'];
        $stamp['offset_y_mm'] = (float) $validated['stamp_offset_y_mm'];
        $overlays['internal_signature'] = $signature;
        $overlays['internal_stamp'] = $stamp;
        $settings['image_overlays'] = $overlays;

        $printFormTemplate->forceFill([
            'settings' => $settings,
            'updated_by' => $request->user()?->id,
        ])->save();

        if ($printFormTemplate->entity_type === 'lead') {
            return to_route('settings.templates.preview-lead-overlay', [
                'printFormTemplate' => $printFormTemplate->id,
                'lead_id' => (int) ($validated['lead_id'] ?? 0),
            ]);
        }

        return to_route('settings.templates.preview-order-overlay', [
            'printFormTemplate' => $printFormTemplate->id,
            'order_id' => (int) ($validated['order_id'] ?? 0),
        ]);
    }

    public function overlayAsset(Request $request, PrintFormTemplate $printFormTemplate, string $overlayKey): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(in_array($overlayKey, ['internal_signature', 'internal_stamp'], true), 404);

        $path = data_get($printFormTemplate->settings, 'image_overlays.'.$overlayKey.'.path');
        $disk = (string) data_get($printFormTemplate->settings, 'image_overlays.'.$overlayKey.'.disk', 'local');

        abort_if(! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path), 404);

        $contents = Storage::disk($disk)->get($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    /**
     * @param  array<string, int>  $context
     * @return array<string, mixed>
     */
    private function buildOverlayPreviewPayload(PrintFormTemplate $printFormTemplate, string $embedUrl, array $context): array
    {
        $settings = is_array($printFormTemplate->settings) ? $printFormTemplate->settings : [];
        $signaturePath = data_get($settings, 'image_overlays.internal_signature.path');
        $stampPath = data_get($settings, 'image_overlays.internal_stamp.path');

        return [
            'documentPreview' => DocumentPreview::inertiaMeta(),
            'templateId' => $printFormTemplate->id,
            'templateName' => $printFormTemplate->name,
            'entityType' => $printFormTemplate->entity_type,
            'embedUrl' => $embedUrl,
            'saveUrl' => route('settings.templates.update-overlay-positions', ['printFormTemplate' => $printFormTemplate->id]),
            'backUrl' => route('settings.templates.index'),
            'orderId' => $context['order_id'] ?? null,
            'leadId' => $context['lead_id'] ?? null,
            'signatureOverlayImageUrl' => is_string($signaturePath) && $signaturePath !== ''
                ? route('settings.templates.overlay-asset', ['printFormTemplate' => $printFormTemplate->id, 'overlayKey' => 'internal_signature'])
                : null,
            'stampOverlayImageUrl' => is_string($stampPath) && $stampPath !== ''
                ? route('settings.templates.overlay-asset', ['printFormTemplate' => $printFormTemplate->id, 'overlayKey' => 'internal_stamp'])
                : null,
            'signatureOffsetXmm' => (float) data_get($settings, 'image_overlays.internal_signature.offset_x_mm', 0),
            'signatureOffsetYmm' => (float) data_get($settings, 'image_overlays.internal_signature.offset_y_mm', 0),
            'stampOffsetXmm' => (float) data_get($settings, 'image_overlays.internal_stamp.offset_x_mm', 0),
            'stampOffsetYmm' => (float) data_get($settings, 'image_overlays.internal_stamp.offset_y_mm', 0),
            'signatureWidthMm' => (float) data_get($settings, 'image_overlays.internal_signature.width_mm', 42),
            'signatureHeightMm' => (float) data_get($settings, 'image_overlays.internal_signature.height_mm', 18),
            'stampWidthMm' => (float) data_get($settings, 'image_overlays.internal_stamp.width_mm', 30),
            'stampHeightMm' => (float) data_get($settings, 'image_overlays.internal_stamp.height_mm', 30),
            'overlayPositioningEnabled' => $printFormTemplate->shouldApplyCrmOverlayOffsets(),
        ];
    }

    public function destroy(Request $request, PrintFormTemplate $printFormTemplate): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $templateId = $printFormTemplate->id;

        if (filled($printFormTemplate->file_path) && filled($printFormTemplate->file_disk)) {
            Storage::disk($printFormTemplate->file_disk)->delete($printFormTemplate->file_path);
        }

        $this->deleteImageOverlayAsset($printFormTemplate, 'internal_signature');
        $this->deleteImageOverlayAsset($printFormTemplate, 'internal_stamp');

        $localDisk = Storage::disk('local');
        foreach ([
            'print-form-templates/'.$templateId,
            'print-form-template-assets/'.$templateId,
            'generated-documents/drafts/'.$templateId,
        ] as $directory) {
            if ($localDisk->exists($directory)) {
                $localDisk->deleteDirectory($directory);
            }
        }

        $printFormTemplate->delete();

        return to_route('settings.templates.index');
    }

    private function persistSourceFile(PrintFormTemplate $template, mixed $uploadedFile): void
    {
        if ($uploadedFile === null) {
            return;
        }

        $hadSourceFile = filled($template->file_path) && filled($template->file_disk);

        if ($hadSourceFile) {
            Storage::disk($template->file_disk)->delete($template->file_path);
        }

        $currentVersion = max(1, (int) $template->version);
        $nextVersion = $hadSourceFile ? $currentVersion + 1 : $currentVersion;

        $extension = $uploadedFile->getClientOriginalExtension() ?: 'docx';
        $fileName = Str::slug($template->code).'-v'.$nextVersion.'.'.$extension;
        $path = $uploadedFile->storeAs('print-form-templates/'.$template->id, $fileName, 'local');

        $settings = is_array($template->settings) ? $template->settings : [];
        $settings['pipeline_status'] = 'uploaded';

        $variables = $this->placeholderExtractor->extractFromDisk('local', $path);
        $settings['variables'] = $variables;
        $settings['variable_mapping'] = $this->filterVariableMapping($settings['variable_mapping'] ?? [], $variables);
        $settings['variable_count'] = count($variables);
        $settings['parsed_at'] = now()->toIso8601String();
        $settings['pipeline_status'] = $variables === [] ? 'uploaded_without_placeholders' : 'placeholders_ready';

        $template->forceFill([
            'file_disk' => 'local',
            'file_path' => $path,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'settings' => $settings,
            'version' => $nextVersion,
        ])->save();
    }

    /**
     * @param  list<array{placeholder?: string, source_path?: ?string}>  $rows
     * @return array<string, string>
     */
    private function normalizeVariableMappings(array $rows): array
    {
        return collect($rows)
            ->mapWithKeys(function (array $row): array {
                $placeholder = trim((string) ($row['placeholder'] ?? ''));
                $sourcePath = trim((string) ($row['source_path'] ?? ''));

                if ($placeholder === '' || $sourcePath === '') {
                    return [];
                }

                return [$placeholder => $sourcePath];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<string>  $variables
     * @return array<string, string>
     */
    private function filterVariableMapping(array $mapping, array $variables): array
    {
        return collect($mapping)
            ->filter(fn (mixed $value, string $key): bool => in_array($key, $variables, true) && is_string($value) && $value !== '')
            ->all();
    }

    /**
     * @param  list<array{placeholder?: string, source_path?: ?string}>  $rows
     */
    private function syncVariableMappings(PrintFormTemplate $template, array $rows): void
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $variables = collect($settings['variables'] ?? [])->filter(fn (mixed $value): bool => is_string($value))->values()->all();

        $settings['variable_mapping'] = $this->filterVariableMapping(
            $this->normalizeVariableMappings($rows),
            $variables
        );

        $template->forceFill([
            'settings' => $settings,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<string, float|string|null>>
     */
    private function normalizeImageOverlaySettings(array $validated): array
    {
        $signaturePlaceholder = trim((string) ($validated['internal_signature_placeholder'] ?? 'internal_signature_image'));
        $stampPlaceholder = trim((string) ($validated['internal_stamp_placeholder'] ?? 'internal_stamp_image'));

        return [
            'apply_crm_overlay_offsets' => (bool) ($validated['apply_crm_overlay_offsets'] ?? true),
            'internal_signature' => [
                'placeholder' => $signaturePlaceholder !== '' ? $signaturePlaceholder : 'internal_signature_image',
                'width_mm' => (float) ($validated['signature_image_width_mm'] ?? 42),
                'height_mm' => (float) ($validated['signature_image_height_mm'] ?? 18),
                'offset_x_mm' => (float) ($validated['signature_image_offset_x_mm'] ?? 0),
                'offset_y_mm' => (float) ($validated['signature_image_offset_y_mm'] ?? 0),
                'path' => null,
                'disk' => null,
            ],
            'internal_stamp' => [
                'placeholder' => $stampPlaceholder !== '' ? $stampPlaceholder : 'internal_stamp_image',
                'width_mm' => (float) ($validated['stamp_image_width_mm'] ?? 30),
                'height_mm' => (float) ($validated['stamp_image_height_mm'] ?? 30),
                'offset_x_mm' => (float) ($validated['stamp_image_offset_x_mm'] ?? 0),
                'offset_y_mm' => (float) ($validated['stamp_image_offset_y_mm'] ?? 0),
                'path' => null,
                'disk' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncImageOverlaySettings(PrintFormTemplate $template, array $validated): void
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $signature = is_array($overlays['internal_signature'] ?? null) ? $overlays['internal_signature'] : [];
        $stamp = is_array($overlays['internal_stamp'] ?? null) ? $overlays['internal_stamp'] : [];

        $applyCrmOverlayOffsets = array_key_exists('apply_crm_overlay_offsets', $validated)
            ? (bool) $validated['apply_crm_overlay_offsets']
            : (bool) ($overlays['apply_crm_overlay_offsets'] ?? true);

        $signaturePlaceholder = trim((string) ($validated['internal_signature_placeholder'] ?? ($signature['placeholder'] ?? 'internal_signature_image')));
        $stampPlaceholder = trim((string) ($validated['internal_stamp_placeholder'] ?? ($stamp['placeholder'] ?? 'internal_stamp_image')));

        $overlays['internal_signature'] = [
            'placeholder' => $signaturePlaceholder !== '' ? $signaturePlaceholder : 'internal_signature_image',
            'width_mm' => (float) ($validated['signature_image_width_mm'] ?? ($signature['width_mm'] ?? 42)),
            'height_mm' => (float) ($validated['signature_image_height_mm'] ?? ($signature['height_mm'] ?? 18)),
            'offset_x_mm' => (float) ($validated['signature_image_offset_x_mm'] ?? ($signature['offset_x_mm'] ?? 0)),
            'offset_y_mm' => (float) ($validated['signature_image_offset_y_mm'] ?? ($signature['offset_y_mm'] ?? 0)),
            'path' => $signature['path'] ?? null,
            'disk' => $signature['disk'] ?? null,
        ];

        $overlays['internal_stamp'] = [
            'placeholder' => $stampPlaceholder !== '' ? $stampPlaceholder : 'internal_stamp_image',
            'width_mm' => (float) ($validated['stamp_image_width_mm'] ?? ($stamp['width_mm'] ?? 30)),
            'height_mm' => (float) ($validated['stamp_image_height_mm'] ?? ($stamp['height_mm'] ?? 30)),
            'offset_x_mm' => (float) ($validated['stamp_image_offset_x_mm'] ?? ($stamp['offset_x_mm'] ?? 0)),
            'offset_y_mm' => (float) ($validated['stamp_image_offset_y_mm'] ?? ($stamp['offset_y_mm'] ?? 0)),
            'path' => $stamp['path'] ?? null,
            'disk' => $stamp['disk'] ?? null,
        ];
        $overlays['apply_crm_overlay_offsets'] = $applyCrmOverlayOffsets;

        $settings['image_overlays'] = $overlays;

        $template->forceFill([
            'settings' => $settings,
        ])->save();
    }

    private function persistImageOverlayFiles(
        PrintFormTemplate $template,
        mixed $signatureImageFile,
        mixed $stampImageFile,
    ): void {
        if ($signatureImageFile instanceof UploadedFile) {
            $this->persistImageOverlayFile($template, 'internal_signature', $signatureImageFile);
        }

        if ($stampImageFile instanceof UploadedFile) {
            $this->persistImageOverlayFile($template, 'internal_stamp', $stampImageFile);
        }
    }

    private function persistImageOverlayFile(
        PrintFormTemplate $template,
        string $overlayKey,
        UploadedFile $uploadedFile,
    ): void {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];
        $overlay = is_array($overlays[$overlayKey] ?? null) ? $overlays[$overlayKey] : [];

        $currentPath = $overlay['path'] ?? null;
        $currentDisk = $overlay['disk'] ?? 'local';
        if (is_string($currentPath) && $currentPath !== '') {
            Storage::disk($currentDisk)->delete($currentPath);
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'png');
        $fileName = Str::slug($template->code).'-'.$overlayKey.'-v'.$template->version.'.'.$extension;
        $path = $uploadedFile->storeAs('print-form-template-assets/'.$template->id, $fileName, 'local');

        $overlay['path'] = $path;
        $overlay['disk'] = 'local';
        $overlays[$overlayKey] = $overlay;
        $settings['image_overlays'] = $overlays;

        $template->forceFill([
            'settings' => $settings,
        ])->save();
    }

    private function deleteImageOverlayAsset(PrintFormTemplate $template, string $overlayKey): void
    {
        $path = data_get($template->settings, 'image_overlays.'.$overlayKey.'.path');
        $disk = data_get($template->settings, 'image_overlays.'.$overlayKey.'.disk', 'local');

        if (is_string($path) && $path !== '') {
            Storage::disk((string) $disk)->delete($path);
        }
    }
}
