<?php

namespace App\Http\Controllers;

use App\Models\LoadingCargoGroup;
use App\Models\LoadingPlannerProject;
use App\Models\TransportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoadingPlannerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureDefaultTransportTemplates();

        $projects = LoadingPlannerProject::query()
            ->where('user_id', $request->user()?->id)
            ->with(['selectedTransportTemplate:id,name,category'])
            ->orderByDesc('updated_at')
            ->get();

        if ($projects->isEmpty()) {
            $this->createStarterProject($request);
            $projects = LoadingPlannerProject::query()
                ->where('user_id', $request->user()?->id)
                ->with(['selectedTransportTemplate:id,name,category'])
                ->orderByDesc('updated_at')
                ->get();
        }

        $selectedId = $request->integer('project');
        $selectedProject = LoadingPlannerProject::query()
            ->where('user_id', $request->user()?->id)
            ->when($selectedId > 0, fn ($query) => $query->whereKey($selectedId))
            ->with(['cargoGroups.items', 'selectedTransportTemplate'])
            ->orderByDesc('updated_at')
            ->first();

        return Inertia::render('Modules/HowMuchFits', [
            'projects' => $projects->map(fn (LoadingPlannerProject $project): array => $this->formatProjectSummary($project))->values(),
            'selectedProject' => $selectedProject ? $this->formatProject($selectedProject) : null,
            'transportTemplates' => TransportTemplate::query()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TransportTemplate $template): array => $this->formatTransportTemplate($template))
                ->values(),
        ]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $template = TransportTemplate::query()->where('is_active', true)->orderBy('sort_order')->first();
        $project = LoadingPlannerProject::query()->create([
            'user_id' => $request->user()?->id,
            'selected_transport_template_id' => $template?->id,
            'name' => $request->string('name')->trim()->toString() ?: 'Новый расчёт',
            'status' => 'draft',
        ]);

        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        $group->items()->create([
            'name' => 'Паллета EUR',
            'package_type' => 'pallet',
            'quantity' => 10,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 1200,
            'weight_kg' => 350,
            'can_rotate' => true,
            'stackable' => false,
            'max_stack' => 1,
            'can_tilt' => false,
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        return to_route('modules.how-much-fits.index', ['project' => $project->id]);
    }

    public function updateProject(Request $request, LoadingPlannerProject $loadingPlannerProject): RedirectResponse
    {
        abort_unless($loadingPlannerProject->user_id === $request->user()?->id, 404);

        $validated = $request->validate($this->projectRules());

        DB::transaction(function () use ($loadingPlannerProject, $validated): void {
            $loadingPlannerProject->update([
                'name' => $validated['name'],
                'selected_transport_template_id' => $validated['selected_transport_template_id'] ?? null,
                'calculation' => $validated['calculation'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $loadingPlannerProject->cargoGroups()->delete();

            foreach ($validated['cargo_groups'] ?? [] as $groupIndex => $groupData) {
                $group = $loadingPlannerProject->cargoGroups()->create([
                    'name' => $groupData['name'],
                    'recipient_name' => $groupData['recipient_name'] ?? null,
                    'color' => $groupData['color'] ?? '#60a5fa',
                    'sort_order' => $groupIndex + 1,
                ]);

                foreach ($groupData['items'] ?? [] as $itemIndex => $itemData) {
                    $group->items()->create([
                        'name' => $itemData['name'],
                        'package_type' => $itemData['package_type'] ?? 'box',
                        'quantity' => $itemData['quantity'],
                        'length_mm' => $itemData['length_mm'],
                        'width_mm' => $itemData['width_mm'],
                        'height_mm' => $itemData['height_mm'],
                        'weight_kg' => $itemData['weight_kg'] ?? 0,
                        'can_rotate' => (bool) ($itemData['can_rotate'] ?? true),
                        'stackable' => (bool) ($itemData['stackable'] ?? false),
                        'max_stack' => $itemData['max_stack'] ?? 1,
                        'can_tilt' => (bool) ($itemData['can_tilt'] ?? false),
                        'color' => $itemData['color'] ?? ($groupData['color'] ?? '#60a5fa'),
                        'sort_order' => $itemIndex + 1,
                    ]);
                }
            }
        });

        return to_route('modules.how-much-fits.index', ['project' => $loadingPlannerProject->id]);
    }

    public function destroyProject(Request $request, LoadingPlannerProject $loadingPlannerProject): RedirectResponse
    {
        abort_unless($loadingPlannerProject->user_id === $request->user()?->id, 404);

        $loadingPlannerProject->delete();

        return to_route('modules.how-much-fits.index');
    }

    public function storeTransportTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->transportTemplateRules());

        TransportTemplate::query()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'is_system' => false,
        ]);

        return back();
    }

    public function updateTransportTemplate(Request $request, TransportTemplate $transportTemplate): RedirectResponse
    {
        $transportTemplate->update($request->validate($this->transportTemplateRules()));

        return back();
    }

    public function destroyTransportTemplate(TransportTemplate $transportTemplate): RedirectResponse
    {
        $transportTemplate->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'selected_transport_template_id' => ['nullable', 'integer', 'exists:transport_templates,id'],
            'notes' => ['nullable', 'string'],
            'calculation' => ['nullable', 'array'],
            'cargo_groups' => ['required', 'array', 'min:1'],
            'cargo_groups.*.name' => ['required', 'string', 'max:255'],
            'cargo_groups.*.recipient_name' => ['nullable', 'string', 'max:255'],
            'cargo_groups.*.color' => ['nullable', 'string', 'max:20'],
            'cargo_groups.*.items' => ['required', 'array', 'min:1'],
            'cargo_groups.*.items.*.name' => ['required', 'string', 'max:255'],
            'cargo_groups.*.items.*.package_type' => ['nullable', Rule::in(['pallet', 'box', 'crate', 'roll', 'bag', 'custom'])],
            'cargo_groups.*.items.*.quantity' => ['required', 'integer', 'min:1', 'max:7000'],
            'cargo_groups.*.items.*.length_mm' => ['required', 'integer', 'min:1', 'max:30000'],
            'cargo_groups.*.items.*.width_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'cargo_groups.*.items.*.height_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'cargo_groups.*.items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cargo_groups.*.items.*.can_rotate' => ['nullable', 'boolean'],
            'cargo_groups.*.items.*.stackable' => ['nullable', 'boolean'],
            'cargo_groups.*.items.*.max_stack' => ['nullable', 'integer', 'min:1', 'max:20'],
            'cargo_groups.*.items.*.can_tilt' => ['nullable', 'boolean'],
            'cargo_groups.*.items.*.color' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transportTemplateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['truck', 'container', 'pallet', 'platform', 'custom'])],
            'length_mm' => ['required', 'integer', 'min:1', 'max:30000'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'height_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_payload_kg' => ['required', 'integer', 'min:0', 'max:100000'],
            'axles_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'settings' => ['nullable', 'array'],
        ];
    }

    private function createStarterProject(Request $request): LoadingPlannerProject
    {
        $template = TransportTemplate::query()->where('is_active', true)->orderBy('sort_order')->first();
        $project = LoadingPlannerProject::query()->create([
            'user_id' => $request->user()?->id,
            'selected_transport_template_id' => $template?->id,
            'name' => 'Мотоциклы',
            'status' => 'draft',
        ]);

        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        foreach ($this->starterCargoItems() as $index => $item) {
            $group->items()->create([...$item, 'sort_order' => $index + 1]);
        }

        return $project;
    }

    private function ensureDefaultTransportTemplates(): void
    {
        if (TransportTemplate::query()->exists()) {
            return;
        }

        foreach ($this->defaultTransportTemplates() as $index => $template) {
            TransportTemplate::query()->create([...$template, 'sort_order' => $index + 1, 'is_system' => true]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultTransportTemplates(): array
    {
        return [
            ['name' => 'Автопоезд: тягач + полуприцеп - тент 13.6 м', 'category' => 'truck', 'length_mm' => 13600, 'width_mm' => 2450, 'height_mm' => 2700, 'max_payload_kg' => 22000, 'axles_count' => 5, 'is_active' => true],
            ['name' => 'Мега-тент 13.6 м', 'category' => 'truck', 'length_mm' => 13600, 'width_mm' => 2450, 'height_mm' => 3000, 'max_payload_kg' => 22000, 'axles_count' => 5, 'is_active' => true],
            ['name' => 'Контейнер 40 ft High Cube', 'category' => 'container', 'length_mm' => 12030, 'width_mm' => 2350, 'height_mm' => 2690, 'max_payload_kg' => 26500, 'axles_count' => null, 'is_active' => true],
            ['name' => 'Контейнер 20 ft', 'category' => 'container', 'length_mm' => 5890, 'width_mm' => 2350, 'height_mm' => 2390, 'max_payload_kg' => 21700, 'axles_count' => null, 'is_active' => true],
            ['name' => 'Газель 4 м', 'category' => 'truck', 'length_mm' => 4000, 'width_mm' => 2000, 'height_mm' => 2100, 'max_payload_kg' => 1500, 'axles_count' => 2, 'is_active' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function starterCargoItems(): array
    {
        return [
            ['name' => 'Electric Motorcycle', 'package_type' => 'box', 'quantity' => 30, 'length_mm' => 1415, 'width_mm' => 455, 'height_mm' => 770, 'weight_kg' => 85, 'can_rotate' => true, 'stackable' => true, 'max_stack' => 2, 'can_tilt' => false, 'color' => '#86efac'],
            ['name' => 'Electric ATV', 'package_type' => 'box', 'quantity' => 24, 'length_mm' => 1150, 'width_mm' => 700, 'height_mm' => 610, 'weight_kg' => 99, 'can_rotate' => true, 'stackable' => true, 'max_stack' => 2, 'can_tilt' => false, 'color' => '#a78bfa'],
            ['name' => 'Electric Bicycle', 'package_type' => 'box', 'quantity' => 18, 'length_mm' => 1350, 'width_mm' => 270, 'height_mm' => 700, 'weight_kg' => 28, 'can_rotate' => true, 'stackable' => true, 'max_stack' => 3, 'can_tilt' => false, 'color' => '#f9e8c9'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProjectSummary(LoadingPlannerProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'transport_name' => $project->selectedTransportTemplate?->name,
            'updated_at' => optional($project->updated_at)->format('d.m.Y'),
            'created_at' => optional($project->created_at)->format('d.m.Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProject(LoadingPlannerProject $project): array
    {
        return [
            ...$this->formatProjectSummary($project),
            'selected_transport_template_id' => $project->selected_transport_template_id,
            'notes' => $project->notes,
            'calculation' => $project->calculation ?? [],
            'cargo_groups' => $project->cargoGroups->map(fn (LoadingCargoGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'recipient_name' => $group->recipient_name,
                'color' => $group->color,
                'items' => $group->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'package_type' => $item->package_type,
                    'quantity' => $item->quantity,
                    'length_mm' => $item->length_mm,
                    'width_mm' => $item->width_mm,
                    'height_mm' => $item->height_mm,
                    'weight_kg' => (float) $item->weight_kg,
                    'can_rotate' => (bool) $item->can_rotate,
                    'stackable' => (bool) $item->stackable,
                    'max_stack' => $item->max_stack,
                    'can_tilt' => (bool) $item->can_tilt,
                    'color' => $item->color,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransportTemplate(TransportTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category,
            'length_mm' => $template->length_mm,
            'width_mm' => $template->width_mm,
            'height_mm' => $template->height_mm,
            'max_payload_kg' => $template->max_payload_kg,
            'axles_count' => $template->axles_count,
            'is_active' => (bool) $template->is_active,
            'is_system' => (bool) $template->is_system,
            'sort_order' => $template->sort_order,
            'settings' => $template->settings ?? [],
        ];
    }
}
