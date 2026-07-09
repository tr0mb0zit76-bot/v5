<?php

namespace App\Http\Controllers;

use App\Enums\SalesScriptNodeKind;
use App\Http\Requests\SalesScripts\SaveGraphRequest;
use App\Http\Requests\SalesScripts\StoreCaptureFieldRequest;
use App\Http\Requests\SalesScripts\StoreNodeRequest;
use App\Http\Requests\SalesScripts\StoreNodeTemplateRequest;
use App\Http\Requests\SalesScripts\StoreScriptRequest;
use App\Http\Requests\SalesScripts\StoreTransitionRequest;
use App\Http\Requests\SalesScripts\StoreVersionRequest;
use App\Http\Requests\SalesScripts\UpdateCaptureFieldRequest;
use App\Http\Requests\SalesScripts\UpdateNodeRequest;
use App\Http\Requests\SalesScripts\UpdateNodeTemplateRequest;
use App\Http\Requests\SalesScripts\UpdateScriptRequest;
use App\Http\Requests\SalesScripts\UpdateTransitionRequest;
use App\Http\Requests\SalesScripts\UpdateVersionRequest;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptNodeTemplate;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Services\SalesScripts\SalesScriptAnalyticsService;
use App\Services\SalesScripts\SalesScriptBodyPlaceholderService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesScriptEditorController extends Controller
{
    public function __construct(
        private readonly SalesScriptBodyPlaceholderService $placeholderService,
        private readonly SalesScriptAnalyticsService $scriptAnalyticsService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', SalesScript::class);

        $scripts = SalesScript::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->orderBy('title')
            ->get()
            ->map(fn (SalesScript $script): array => $this->serializeScriptForList($script));

        return Inertia::render('SalesScripts/Editor/Index', [
            'scripts' => $scripts,
            'nodeKinds' => $this->nodeKindOptions(),
        ]);
    }

    public function storeScript(StoreScriptRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesScript::class);

        SalesScript::query()->create($request->validated());

        return to_route('scripts.editor.index')->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий создан. Добавьте версию и шаги.',
        ]);
    }

    public function updateScript(UpdateScriptRequest $request, SalesScript $sales_script): RedirectResponse
    {
        $script = $sales_script;
        $this->authorize('update', $script);

        $script->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий сохранён.',
        ]);
    }

    public function destroyScript(SalesScript $sales_script): RedirectResponse
    {
        $script = $sales_script;
        $this->authorize('delete', $script);

        $script->delete();

        return to_route('scripts.editor.index')->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий удалён.',
        ]);
    }

    public function storeVersion(StoreVersionRequest $request, SalesScript $sales_script): RedirectResponse
    {
        $script = $sales_script;
        $this->authorize('update', $script);

        $version = DB::transaction(function () use ($request, $script): SalesScriptVersion {
            $nextNumber = (int) $script->versions()->max('version_number') + 1;
            $newVersion = $script->versions()->create([
                'version_number' => max(1, $nextNumber),
                'published_at' => null,
                'is_active' => false,
                'entry_node_key' => null,
            ]);

            $sourceId = $request->validated('duplicate_from_version_id');
            if ($sourceId !== null) {
                /** @var SalesScriptVersion $source */
                $source = $script->versions()->whereKey($sourceId)->firstOrFail();
                $idMap = [];
                foreach ($source->nodes()->orderBy('sort_order')->orderBy('id')->get() as $node) {
                    $copy = $newVersion->nodes()->create([
                        'client_key' => $node->client_key,
                        'kind' => $node->kind,
                        'body' => $node->body,
                        'hint' => $node->hint,
                        'tags' => $node->tags ?? [],
                        'capture_field_codes' => $node->capture_field_codes ?? [],
                        'sort_order' => $node->sort_order,
                        'canvas_x' => $node->canvas_x,
                        'canvas_y' => $node->canvas_y,
                    ]);
                    $idMap[$node->id] = $copy->id;
                }
                $newVersion->update([
                    'entry_node_key' => $source->entry_node_key,
                ]);
                foreach ($source->transitions()->orderBy('sort_order')->orderBy('id')->get() as $transition) {
                    $newVersion->transitions()->create([
                        'from_node_id' => $idMap[$transition->from_node_id],
                        'to_node_id' => $idMap[$transition->to_node_id],
                        'target_type' => $transition->target_type ?? 'node',
                        'target_sales_script_version_id' => $transition->target_sales_script_version_id,
                        'sales_script_reaction_class_id' => $transition->sales_script_reaction_class_id,
                        'customer_label' => $transition->customer_label,
                        'conversation_effect' => $transition->conversation_effect,
                        'momentum_delta' => $transition->momentum_delta,
                        'next_move_preview' => $transition->next_move_preview,
                        'sort_order' => $transition->sort_order,
                    ]);
                }
            }

            return $newVersion;
        });

        return to_route('scripts.editor.versions.show', $version)->with('flash', [
            'type' => 'success',
            'message' => 'Версия создана.',
        ]);
    }

    public function showVersion(SalesScriptVersion $sales_script_version): Response
    {
        return $this->renderGraphEditor($sales_script_version);
    }

    public function showGraph(SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $this->authorize('view', $sales_script_version);

        return to_route('scripts.editor.versions.show', $sales_script_version);
    }

    public function analytics(Request $request, SalesScriptVersion $sales_script_version): Response
    {
        $this->authorize('view', $sales_script_version);
        abort_unless($this->canViewAnalytics($request), 403);

        $days = max(1, min(365, (int) $request->integer('days', (int) config('sales_scripts.analytics.default_days', 30))));
        $report = $this->scriptAnalyticsService->reportForVersion((int) $sales_script_version->id, $days);

        $sales_script_version->loadMissing('script');

        return Inertia::render('SalesScripts/Editor/Analytics', [
            'payload' => $this->serializeVersionPayload($sales_script_version),
            'report' => $report,
            'days' => $days,
        ]);
    }

    public function exportAnalytics(Request $request, SalesScriptVersion $sales_script_version): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $sales_script_version);
        abort_unless($this->canViewAnalytics($request), 403);

        $days = max(1, min(365, (int) $request->integer('days', (int) config('sales_scripts.analytics.default_days', 30))));
        $csv = $this->scriptAnalyticsService->exportCsvForVersion((int) $sales_script_version->id, $days);
        $sales_script_version->loadMissing('script');
        $filename = sprintf(
            'script-analytics-v%d-%s.csv',
            $sales_script_version->version_number,
            now()->format('Y-m-d'),
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function updateGraph(SaveGraphRequest $request, SalesScriptVersion $sales_script_version): RedirectResponse|JsonResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $validated = $request->validated();
        $nodesPayload = collect($validated['nodes'])->values();
        $transitionsPayload = collect($validated['transitions'] ?? [])->values();
        $nodeKeys = $nodesPayload->pluck('client_key');

        if ($nodeKeys->unique()->count() !== $nodeKeys->count()) {
            throw ValidationException::withMessages([
                'nodes' => 'Ключи шагов должны быть уникальными.',
            ]);
        }

        $entryNodeKey = $validated['entry_node_key'] ?? null;
        if ($entryNodeKey !== null && $entryNodeKey !== '' && ! $nodeKeys->contains($entryNodeKey)) {
            throw ValidationException::withMessages([
                'entry_node_key' => 'Стартовый ключ должен совпадать с ключом одного из шагов.',
            ]);
        }

        $invalidTransition = $transitionsPayload->first(function (array $transition) use ($nodeKeys): bool {
            return ! $nodeKeys->contains($transition['from_client_key']) || ! $nodeKeys->contains($transition['to_client_key']);
        });
        if ($invalidTransition !== null) {
            throw ValidationException::withMessages([
                'transitions' => 'Каждый переход должен ссылаться на существующие шаги.',
            ]);
        }

        $invalidScriptTransition = $transitionsPayload->first(function (array $transition): bool {
            $targetType = (string) ($transition['target_type'] ?? 'node');

            return $targetType === 'script' && empty($transition['target_sales_script_version_id']);
        });
        if ($invalidScriptTransition !== null) {
            throw ValidationException::withMessages([
                'transitions' => 'Для перехода в другой сценарий выберите опубликованную версию сценария.',
            ]);
        }

        $targetVersionIds = $transitionsPayload
            ->filter(fn (array $transition): bool => (string) ($transition['target_type'] ?? 'node') === 'script')
            ->pluck('target_sales_script_version_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        if ($targetVersionIds->isNotEmpty()) {
            $publishedTargetCount = SalesScriptVersion::query()
                ->whereIn('id', $targetVersionIds)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->count();

            if ($publishedTargetCount !== $targetVersionIds->count()) {
                throw ValidationException::withMessages([
                    'transitions' => 'Переходить можно только в опубликованные сценарии.',
                ]);
            }
        }

        DB::transaction(function () use ($version, $nodesPayload, $transitionsPayload, $entryNodeKey): void {
            $existingByKey = $version->nodes()->get()->keyBy('client_key');
            $seenKeys = [];
            $nodesByKey = [];

            foreach ($nodesPayload as $index => $nodeData) {
                $clientKey = (string) $nodeData['client_key'];
                $seenKeys[] = $clientKey;

                /** @var SalesScriptNode|null $node */
                $node = $existingByKey->get($clientKey);
                $attributes = [
                    'kind' => $nodeData['kind'],
                    'body' => $nodeData['body'],
                    'body_variant_b' => $nodeData['body_variant_b'] ?? null,
                    'ab_enabled' => (bool) ($nodeData['ab_enabled'] ?? false),
                    'ab_variant_b_weight' => (int) ($nodeData['ab_variant_b_weight'] ?? 50),
                    'hint' => $nodeData['hint'] ?? null,
                    'tags' => $this->normalizeNodeTags($nodeData['tags'] ?? null),
                    'capture_field_codes' => $this->normalizeCaptureFieldCodes($nodeData['capture_field_codes'] ?? null),
                    'sort_order' => $nodeData['sort_order'] ?? $index,
                    'canvas_x' => $nodeData['canvas_x'] ?? null,
                    'canvas_y' => $nodeData['canvas_y'] ?? null,
                ];

                if ($node === null) {
                    $node = $version->nodes()->create([
                        'client_key' => $clientKey,
                        ...$attributes,
                    ]);
                } else {
                    $node->update($attributes);
                }

                $nodesByKey[$clientKey] = $node;
            }

            $version->nodes()
                ->whereNotIn('client_key', $seenKeys)
                ->delete();

            $version->transitions()->delete();

            foreach ($transitionsPayload as $index => $transitionData) {
                $targetType = (string) ($transitionData['target_type'] ?? 'node');
                $version->transitions()->create([
                    'from_node_id' => $nodesByKey[$transitionData['from_client_key']]->id,
                    'to_node_id' => $nodesByKey[$transitionData['to_client_key']]->id,
                    'target_type' => $targetType,
                    'target_sales_script_version_id' => $targetType === 'script'
                        ? ($transitionData['target_sales_script_version_id'] ?? null)
                        : null,
                    'sales_script_reaction_class_id' => $transitionData['sales_script_reaction_class_id'] ?? null,
                    'customer_label' => $transitionData['customer_label'] ?? null,
                    'conversation_effect' => $transitionData['conversation_effect'] ?? null,
                    'momentum_delta' => $transitionData['momentum_delta'] ?? null,
                    'next_move_preview' => $transitionData['next_move_preview'] ?? null,
                    'sort_order' => $transitionData['sort_order'] ?? $index,
                ]);
            }

            $version->update([
                'entry_node_key' => $entryNodeKey === '' ? null : $entryNodeKey,
            ]);
        });

        if ($request->boolean('autosave')) {
            return response()->json([
                'ok' => true,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return to_route('scripts.editor.versions.show', $version)->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий сохранён.',
        ]);
    }

    public function updateVersion(UpdateVersionRequest $request, SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $version->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Версия обновлена.',
        ]);
    }

    public function publishVersion(SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $keys = $version->nodes()->pluck('client_key')->filter()->values()->all();
        if ($version->entry_node_key !== null && $version->entry_node_key !== '' && ! in_array($version->entry_node_key, $keys, true)) {
            throw ValidationException::withMessages([
                'entry_node_key' => 'Стартовый ключ должен совпадать с ключом одного из шагов.',
            ]);
        }

        if ($version->nodes()->doesntExist()) {
            throw ValidationException::withMessages([
                'version' => 'Нельзя опубликовать версию без шагов.',
            ]);
        }

        DB::transaction(function () use ($version): void {
            SalesScriptVersion::query()
                ->where('sales_script_id', $version->sales_script_id)
                ->whereKeyNot($version->id)
                ->update(['is_active' => false]);

            $version->update([
                'is_active' => true,
                'published_at' => $version->published_at ?? now(),
            ]);
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Версия опубликована и стала активной для сценария.',
        ]);
    }

    public function unpublishVersion(SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $version->update([
            'is_active' => false,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Версия снята с публикации (не показывается при старте сессии).',
        ]);
    }

    public function storeNode(StoreNodeRequest $request, SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $version->nodes()->create($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг добавлен.',
        ]);
    }

    public function updateNode(UpdateNodeRequest $request, SalesScriptNode $sales_script_node): RedirectResponse
    {
        $node = $sales_script_node;
        $this->authorize('update', $node);

        $node->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг сохранён.',
        ]);
    }

    public function destroyNode(SalesScriptNode $sales_script_node): RedirectResponse
    {
        $node = $sales_script_node;
        $this->authorize('delete', $node);

        $node->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг удалён.',
        ]);
    }

    public function storeTransition(StoreTransitionRequest $request, SalesScriptVersion $sales_script_version): RedirectResponse
    {
        $version = $sales_script_version;
        $this->authorize('update', $version);

        $data = $request->validated();
        $this->assertTransitionNodesBelongToVersion($version, (int) $data['from_node_id'], (int) $data['to_node_id']);
        $data = $this->normalizeTransitionPayload($data);

        $version->transitions()->create($data);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход добавлен.',
        ]);
    }

    public function updateTransition(
        UpdateTransitionRequest $request,
        SalesScriptTransition $sales_script_transition,
    ): RedirectResponse {
        $transition = $sales_script_transition;
        $this->authorize('update', $transition);

        $data = $request->validated();
        $version = $transition->version;
        $this->assertTransitionNodesBelongToVersion($version, (int) $data['from_node_id'], (int) $data['to_node_id']);
        $data = $this->normalizeTransitionPayload($data);

        $transition->update($data);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход сохранён.',
        ]);
    }

    public function destroyTransition(SalesScriptTransition $sales_script_transition): RedirectResponse
    {
        $transition = $sales_script_transition;
        $this->authorize('delete', $transition);

        $transition->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход удалён.',
        ]);
    }

    public function storeCaptureField(StoreCaptureFieldRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', SalesScript::class);

        $validated = $request->validated();
        $maxSort = (int) SalesScriptCaptureField::query()->max('sort_order');

        SalesScriptCaptureField::query()->create([
            'code' => $this->placeholderService->normalizeCode((string) $validated['code']),
            'label' => trim((string) $validated['label']),
            'value_type' => $validated['value_type'] ?? 'text',
            'description' => $validated['description'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Поле добавлено в справочник.',
        ]);
    }

    public function updateCaptureField(
        UpdateCaptureFieldRequest $request,
        SalesScriptCaptureField $sales_script_capture_field,
    ): RedirectResponse {
        $this->authorize('viewAny', SalesScript::class);

        $sales_script_capture_field->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Поле обновлено.',
        ]);
    }

    public function destroyCaptureField(SalesScriptCaptureField $sales_script_capture_field): RedirectResponse
    {
        $this->authorize('viewAny', SalesScript::class);

        $sales_script_capture_field->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Поле удалено из справочника.',
        ]);
    }

    public function storeNodeTemplate(StoreNodeTemplateRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', SalesScript::class);

        $validated = $request->validated();

        SalesScriptNodeTemplate::query()->create([
            'title' => trim((string) $validated['title']),
            'kind' => $validated['kind'],
            'body' => $validated['body'],
            'hint' => $validated['hint'] ?? null,
            'tags' => $this->normalizeNodeTags($validated['tags'] ?? null),
            'capture_field_codes' => $this->normalizeCaptureFieldCodes($validated['capture_field_codes'] ?? null),
            'default_transitions' => $validated['default_transitions'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаблон блока сохранён.',
        ]);
    }

    public function updateNodeTemplate(
        UpdateNodeTemplateRequest $request,
        SalesScriptNodeTemplate $sales_script_node_template,
    ): RedirectResponse {
        $this->authorize('viewAny', SalesScript::class);

        $validated = $request->validated();

        $sales_script_node_template->update([
            'title' => trim((string) $validated['title']),
            'kind' => $validated['kind'],
            'body' => $validated['body'],
            'hint' => $validated['hint'] ?? null,
            'tags' => $this->normalizeNodeTags($validated['tags'] ?? null),
            'capture_field_codes' => $this->normalizeCaptureFieldCodes($validated['capture_field_codes'] ?? null),
            'default_transitions' => $validated['default_transitions'] ?? null,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаблон обновлён.',
        ]);
    }

    public function destroyNodeTemplate(SalesScriptNodeTemplate $sales_script_node_template): RedirectResponse
    {
        $this->authorize('viewAny', SalesScript::class);

        $sales_script_node_template->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаблон удалён.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScriptForList(SalesScript $script): array
    {
        return [
            'id' => $script->id,
            'title' => $script->title,
            'description' => $script->description,
            'channel' => $script->channel,
            'tags' => $script->tags ?? [],
            'versions' => $script->versions->map(fn (SalesScriptVersion $v): array => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'is_active' => $v->is_active,
                'published_at' => $v->published_at?->toIso8601String(),
                'entry_node_key' => $v->entry_node_key,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVersionPayload(SalesScriptVersion $version): array
    {
        $version->loadMissing(['script', 'nodes', 'transitions']);

        return [
            'version' => [
                'id' => $version->id,
                'sales_script_id' => $version->sales_script_id,
                'version_number' => $version->version_number,
                'is_active' => $version->is_active,
                'published_at' => $version->published_at?->toIso8601String(),
                'entry_node_key' => $version->entry_node_key,
            ],
            'script' => [
                'id' => $version->script->id,
                'title' => $version->script->title,
                'description' => $version->script->description,
                'channel' => $version->script->channel,
                'tags' => $version->script->tags ?? [],
            ],
            'nodes' => $version->nodes->sortBy(['sort_order', 'id'])->values()->map(fn (SalesScriptNode $n): array => [
                'id' => $n->id,
                'client_key' => $n->client_key,
                'kind' => $n->kind->value,
                'body' => $n->body,
                'body_variant_b' => $n->body_variant_b,
                'ab_enabled' => (bool) $n->ab_enabled,
                'ab_variant_b_weight' => (int) ($n->ab_variant_b_weight ?? 50),
                'hint' => $n->hint,
                'tags' => $n->tags ?? [],
                'capture_field_codes' => $n->capture_field_codes ?? [],
                'sort_order' => $n->sort_order,
                'canvas_x' => $n->canvas_x,
                'canvas_y' => $n->canvas_y,
            ]),
            'transitions' => $version->transitions->sortBy(['sort_order', 'id'])->values()->map(fn (SalesScriptTransition $t): array => [
                'id' => $t->id,
                'from_node_id' => $t->from_node_id,
                'to_node_id' => $t->to_node_id,
                'target_type' => $t->target_type ?? 'node',
                'target_sales_script_version_id' => $t->target_sales_script_version_id,
                'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                'customer_label' => $t->customer_label,
                'conversation_effect' => $t->conversation_effect,
                'momentum_delta' => $t->momentum_delta,
                'next_move_preview' => $t->next_move_preview,
                'sort_order' => $t->sort_order,
            ]),
        ];
    }

    private function renderGraphEditor(SalesScriptVersion $sales_script_version): Response
    {
        $version = $sales_script_version;
        $this->authorize('view', $version);

        $version->load(['script']);

        return Inertia::render('SalesScripts/Editor/Graph', [
            'payload' => $this->serializeVersionPayload($version),
            'reactionClasses' => SalesScriptReactionClass::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'key', 'label']),
            'nodeKinds' => $this->nodeKindOptions(),
            'captureFields' => SalesScriptCaptureField::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'code', 'label', 'value_type', 'description']),
            'nodeTemplates' => SalesScriptNodeTemplate::query()
                ->orderByDesc('id')
                ->get(['id', 'title', 'kind', 'body', 'hint', 'tags', 'capture_field_codes', 'default_transitions']),
            'targetVersions' => SalesScriptVersion::query()
                ->with('script')
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->orderByDesc('version_number')
                ->get()
                ->sortBy(fn (SalesScriptVersion $version): string => mb_strtolower((string) ($version->script?->title ?? ''), 'UTF-8'))
                ->map(fn (SalesScriptVersion $version): array => [
                    'id' => $version->id,
                    'script_id' => $version->sales_script_id,
                    'title' => $version->script?->title ?? 'Сценарий #'.$version->sales_script_id,
                    'version_number' => $version->version_number,
                ])
                ->values(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function nodeKindOptions(): array
    {
        return [
            ['value' => SalesScriptNodeKind::Say->value, 'label' => 'Сказать (реплика)'],
            ['value' => SalesScriptNodeKind::Ask->value, 'label' => 'Спросить (вопрос)'],
            ['value' => SalesScriptNodeKind::Branch->value, 'label' => 'Ветвление'],
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeNodeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $trimmed = trim($tag);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = mb_strtolower($trimmed, 'UTF-8');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    private function normalizeCaptureFieldCodes(mixed $codes): array
    {
        if (! is_array($codes)) {
            return [];
        }

        $normalized = [];
        foreach ($codes as $code) {
            if (! is_string($code)) {
                continue;
            }

            $value = $this->placeholderService->normalizeCode($code);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function assertTransitionNodesBelongToVersion(SalesScriptVersion $version, int $fromNodeId, int $toNodeId): void
    {
        $count = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->whereIn('id', [$fromNodeId, $toNodeId])
            ->count();

        if ($count !== 2) {
            throw ValidationException::withMessages([
                'from_node_id' => 'Оба шага должны принадлежать этой версии сценария.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTransitionPayload(array $data): array
    {
        $data['target_type'] = (string) ($data['target_type'] ?? 'node');
        if ($data['target_type'] !== 'script') {
            $data['target_sales_script_version_id'] = null;

            return $data;
        }

        $targetVersionId = (int) ($data['target_sales_script_version_id'] ?? 0);
        $targetVersionExists = $targetVersionId > 0
            && SalesScriptVersion::query()
                ->whereKey($targetVersionId)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->exists();

        if (! $targetVersionExists) {
            throw ValidationException::withMessages([
                'target_sales_script_version_id' => 'Выберите опубликованную версию целевого сценария.',
            ]);
        }

        return $data;
    }

    private function canViewAnalytics(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return RoleAccess::canManageSalesScripts($user)
            || RoleAccess::canViewTrainerAnalytics($user)
            || RoleAccess::canViewAiAnalytics($user);
    }
}
