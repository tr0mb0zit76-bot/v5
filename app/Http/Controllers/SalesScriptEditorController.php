<?php

namespace App\Http\Controllers;

use App\Enums\SalesScriptNodeKind;
use App\Http\Requests\SalesScripts\StoreNodeRequest;
use App\Http\Requests\SalesScripts\StoreScriptRequest;
use App\Http\Requests\SalesScripts\StoreTransitionRequest;
use App\Http\Requests\SalesScripts\StoreVersionRequest;
use App\Http\Requests\SalesScripts\UpdateNodeRequest;
use App\Http\Requests\SalesScripts\UpdateScriptRequest;
use App\Http\Requests\SalesScripts\UpdateTransitionRequest;
use App\Http\Requests\SalesScripts\UpdateVersionRequest;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesScriptEditorController extends Controller
{
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

    public function updateScript(UpdateScriptRequest $request, SalesScript $script): RedirectResponse
    {
        $this->authorize('update', $script);

        $script->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий сохранён.',
        ]);
    }

    public function destroyScript(SalesScript $script): RedirectResponse
    {
        $this->authorize('delete', $script);

        $script->delete();

        return to_route('scripts.editor.index')->with('flash', [
            'type' => 'success',
            'message' => 'Сценарий удалён.',
        ]);
    }

    public function storeVersion(StoreVersionRequest $request, SalesScript $script): RedirectResponse
    {
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
                        'sort_order' => $node->sort_order,
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
                        'sales_script_reaction_class_id' => $transition->sales_script_reaction_class_id,
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

    public function showVersion(SalesScriptVersion $version): Response
    {
        $this->authorize('view', $version);

        $version->load(['script']);

        return Inertia::render('SalesScripts/Editor/Version', [
            'payload' => $this->serializeVersionPayload($version),
            'reactionClasses' => SalesScriptReactionClass::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'key', 'label']),
            'nodeKinds' => $this->nodeKindOptions(),
        ]);
    }

    public function updateVersion(UpdateVersionRequest $request, SalesScriptVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $version->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Версия обновлена.',
        ]);
    }

    public function publishVersion(SalesScriptVersion $version): RedirectResponse
    {
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

    public function unpublishVersion(SalesScriptVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $version->update([
            'is_active' => false,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Версия снята с публикации (не показывается при старте сессии).',
        ]);
    }

    public function storeNode(StoreNodeRequest $request, SalesScriptVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $version->nodes()->create($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг добавлен.',
        ]);
    }

    public function updateNode(UpdateNodeRequest $request, SalesScriptNode $node): RedirectResponse
    {
        $this->authorize('update', $node);

        $node->update($request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг сохранён.',
        ]);
    }

    public function destroyNode(SalesScriptNode $node): RedirectResponse
    {
        $this->authorize('delete', $node);

        $node->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Шаг удалён.',
        ]);
    }

    public function storeTransition(StoreTransitionRequest $request, SalesScriptVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $data = $request->validated();
        $this->assertTransitionNodesBelongToVersion($version, (int) $data['from_node_id'], (int) $data['to_node_id']);

        $version->transitions()->create($data);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход добавлен.',
        ]);
    }

    public function updateTransition(UpdateTransitionRequest $request, SalesScriptTransition $transition): RedirectResponse
    {
        $this->authorize('update', $transition);

        $data = $request->validated();
        $version = $transition->version;
        $this->assertTransitionNodesBelongToVersion($version, (int) $data['from_node_id'], (int) $data['to_node_id']);

        $transition->update($data);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход сохранён.',
        ]);
    }

    public function destroyTransition(SalesScriptTransition $transition): RedirectResponse
    {
        $this->authorize('delete', $transition);

        $transition->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Переход удалён.',
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
                'hint' => $n->hint,
                'sort_order' => $n->sort_order,
            ]),
            'transitions' => $version->transitions->sortBy(['sort_order', 'id'])->values()->map(fn (SalesScriptTransition $t): array => [
                'id' => $t->id,
                'from_node_id' => $t->from_node_id,
                'to_node_id' => $t->to_node_id,
                'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                'sort_order' => $t->sort_order,
            ]),
        ];
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
}
