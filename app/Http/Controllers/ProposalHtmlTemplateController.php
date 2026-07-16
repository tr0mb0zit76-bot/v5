<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalHtmlTemplateRequest;
use App\Http\Requests\UpdateProposalHtmlTemplateRequest;
use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Models\ProposalHtmlTemplateVariable;
use App\Services\Commercial\LeadProposalHtmlRenderer;
use App\Services\Commercial\ProposalEmlImportService;
use App\Support\LeadViewAuthorization;
use App\Support\ProposalHtmlEmailDocumentNormalizer;
use App\Support\ProposalHtmlTemplateVariableCatalog;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProposalHtmlTemplateController extends Controller
{
    public function __construct(
        private readonly ProposalHtmlTemplateVariableCatalog $variableCatalog,
        private readonly LeadProposalHtmlRenderer $htmlRenderer,
        private readonly ProposalEmlImportService $emlImportService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($this->canManage($request), 403);
        abort_unless(Schema::hasTable('proposal_html_templates'), 404);

        $templates = ProposalHtmlTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ProposalHtmlTemplate $template): array => $this->serializeTemplate($template))
            ->values();

        return Inertia::render('Modules/ProposalTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canManage($request), 403);

        return Inertia::render('Modules/ProposalTemplates/Editor', [
            'template' => null,
            'variables' => $this->variablesForUi(),
            'previewLeads' => $this->previewLeadsForUi(),
        ]);
    }

    public function edit(Request $request, ProposalHtmlTemplate $proposalHtmlTemplate): Response
    {
        abort_unless($this->canManage($request), 403);

        // Full-document .eml HTML → body fragment + css, иначе GrapesJS теряет <head>-стили.
        if (Str::contains(strtolower((string) $proposalHtmlTemplate->html_body), '<html')
            || Str::contains(strtolower((string) $proposalHtmlTemplate->html_body), '<head')) {
            $this->emlImportService->normalizeStoredTemplate($proposalHtmlTemplate);
            $proposalHtmlTemplate->refresh();
        }

        return Inertia::render('Modules/ProposalTemplates/Editor', [
            'template' => $this->serializeTemplate($proposalHtmlTemplate),
            'variables' => $this->variablesForUi(),
            'previewLeads' => $this->previewLeadsForUi(),
        ]);
    }

    public function store(StoreProposalHtmlTemplateRequest $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $validated = $request->validated();
        $slug = filled($validated['slug'] ?? null)
            ? (string) $validated['slug']
            : Str::slug((string) $validated['name']);

        $normalized = $this->normalizeHtmlPayload(
            (string) $validated['html_body'],
            isset($validated['css_inline']) ? (string) $validated['css_inline'] : null,
        );

        $template = ProposalHtmlTemplate::query()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($slug),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'html_body' => $normalized['html_body'],
            'css_inline' => $normalized['css_inline'],
            'version' => 1,
            'published_at' => now(),
            'owner_user_id' => $request->user()?->id,
            'visibility' => $validated['visibility'] ?? 'workspace',
        ]);

        return to_route('modules.proposal-templates.edit', $template)
            ->with('flash', ['type' => 'success', 'message' => 'HTML-шаблон КП создан.']);
    }

    public function update(UpdateProposalHtmlTemplateRequest $request, ProposalHtmlTemplate $proposalHtmlTemplate): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $validated = $request->validated();
        $htmlBody = $validated['html_body'] ?? $proposalHtmlTemplate->html_body;
        $cssInline = array_key_exists('css_inline', $validated)
            ? $validated['css_inline']
            : $proposalHtmlTemplate->css_inline;
        $normalized = $this->normalizeHtmlPayload(
            (string) $htmlBody,
            is_string($cssInline) ? $cssInline : null,
        );

        $proposalHtmlTemplate->fill([
            'name' => $validated['name'] ?? $proposalHtmlTemplate->name,
            'slug' => $validated['slug'] ?? $proposalHtmlTemplate->slug,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $proposalHtmlTemplate->is_active,
            'html_body' => $normalized['html_body'],
            'css_inline' => $normalized['css_inline'],
            'visibility' => $validated['visibility'] ?? $proposalHtmlTemplate->visibility,
        ]);

        if ($proposalHtmlTemplate->isDirty(['html_body', 'css_inline', 'name'])) {
            $proposalHtmlTemplate->version = (int) $proposalHtmlTemplate->version + 1;
            $proposalHtmlTemplate->published_at = now();
        }

        $proposalHtmlTemplate->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Шаблон сохранён.']);
    }

    public function preview(Request $request, ProposalHtmlTemplate $proposalHtmlTemplate, Lead $lead): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($this->canManage($request), 403);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless($proposalHtmlTemplate->is_active, 404);

        $rendered = $this->htmlRenderer->render($proposalHtmlTemplate, $lead);

        return response($rendered['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="proposal-preview.html"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function previewLeadsForUi(): array
    {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        return Lead::query()
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'number', 'title'])
            ->map(function (Lead $lead): array {
                $number = trim((string) ($lead->number ?? ''));
                $title = trim((string) ($lead->title ?? ''));
                $label = $number !== '' ? $number : "Лид #{$lead->id}";

                if ($title !== '') {
                    $label .= ' — '.$title;
                }

                return [
                    'id' => (int) $lead->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{path: string, label: string, group_name: string}>
     */
    private function variablesForUi(): array
    {
        if (Schema::hasTable('proposal_html_template_variables')) {
            $rows = ProposalHtmlTemplateVariable::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['path', 'label', 'group_name']);

            if ($rows->isNotEmpty()) {
                return $rows->map(fn (ProposalHtmlTemplateVariable $row): array => [
                    'path' => $row->path,
                    'label' => $row->label,
                    'group_name' => $row->group_name,
                ])->all();
            }
        }

        return $this->variableCatalog->optionsForUi();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(ProposalHtmlTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'is_active' => $template->is_active,
            'html_body' => $template->html_body,
            'css_inline' => $template->css_inline,
            'email_assets_count' => is_array($template->email_assets) ? count($template->email_assets) : 0,
            'version' => $template->version,
            'published_at' => optional($template->published_at)?->toIso8601String(),
            'visibility' => $template->visibility,
        ];
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'template';
        $candidate = $slug;
        $suffix = 1;

        while (ProposalHtmlTemplate::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array{html_body: string, css_inline: string|null}
     */
    private function normalizeHtmlPayload(string $html, ?string $css): array
    {
        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html, $css);

        return [
            'html_body' => $normalized['body'],
            'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : null,
        ];
    }

    private function canManage(Request $request): bool
    {
        return RoleAccess::canAccessSettingsSystem($request->user());
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }
}
