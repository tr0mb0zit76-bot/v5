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
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    'variable_mapping' => data_get($template->settings, 'variable_mapping', (object) []),
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
                'pipeline_status' => 'uploaded',
            ],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->persistSourceFile($template, $request->file('source_file'));

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

        $this->persistSourceFile($printFormTemplate, $request->file('source_file'));

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

        $order = Order::query()->findOrFail($validated['order_id']);
        $generatedFile = $this->orderDraftService->generate($printFormTemplate, $order);

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

        $lead = Lead::query()->findOrFail($validated['lead_id']);
        $generatedFile = $this->leadDraftService->generate($printFormTemplate, $lead);

        return $this->draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    public function destroy(Request $request, PrintFormTemplate $printFormTemplate): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        if (filled($printFormTemplate->file_path) && filled($printFormTemplate->file_disk)) {
            Storage::disk($printFormTemplate->file_disk)->delete($printFormTemplate->file_path);
        }

        $printFormTemplate->delete();

        return to_route('settings.templates.index');
    }

    private function persistSourceFile(PrintFormTemplate $template, mixed $uploadedFile): void
    {
        if ($uploadedFile === null) {
            return;
        }

        if (filled($template->file_path) && filled($template->file_disk)) {
            Storage::disk($template->file_disk)->delete($template->file_path);
        }

        $extension = $uploadedFile->getClientOriginalExtension() ?: 'docx';
        $fileName = Str::slug($template->code).'-v'.$template->version.'.'.$extension;
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
}
