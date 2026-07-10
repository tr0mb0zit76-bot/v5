<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LoadingCargoGroup;
use App\Models\LoadingPlannerProject;
use App\Models\Order;
use App\Models\TransportTemplate;
use App\Models\User;
use App\Services\LoadingPlanner\LoadingPlannerCargoSeedService;
use App\Support\LoadingPlannerAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoadingPlannerController extends Controller
{
    public function __construct(
        private readonly LoadingPlannerCargoSeedService $cargoSeedService,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureDefaultTransportTemplates();

        $user = $request->user();
        abort_if($user === null, 403);

        $linkContext = $this->resolveLinkContext($request);
        $leadFilterId = $linkContext['lead_id'] ?? null;
        $orderFilterId = $linkContext['order_id'] ?? null;

        $projectsQuery = LoadingPlannerAccess::applyVisibleProjectsScope(
            LoadingPlannerProject::query(),
            $user,
        )
            ->with([
                'selectedTransportTemplate:id,name,category',
                'user:id,name',
                'lead:id,number,title',
                'order:id,order_number,lead_id',
            ])
            ->when($orderFilterId !== null, fn ($query) => $query->where('order_id', $orderFilterId))
            ->when($leadFilterId !== null && $orderFilterId === null, fn ($query) => $query->where('lead_id', $leadFilterId))
            ->orderByDesc('updated_at');

        $projects = $projectsQuery->get();

        if ($projects->isEmpty() && $linkContext === null && ! LoadingPlannerAccess::canViewAllProjects($user)) {
            $this->createStarterProject($request);
            $projects = LoadingPlannerAccess::applyVisibleProjectsScope(
                LoadingPlannerProject::query(),
                $user,
            )
                ->with([
                    'selectedTransportTemplate:id,name,category',
                    'user:id,name',
                    'lead:id,number,title',
                    'order:id,order_number,lead_id',
                ])
                ->orderByDesc('updated_at')
                ->get();
        }

        $selectedId = $request->integer('project');
        $selectedProject = $this->resolveSelectedProject(
            $user,
            $selectedId,
            $leadFilterId,
            $orderFilterId,
        );

        if ($selectedProject === null && $projects->isNotEmpty()) {
            $selectedProject = $this->resolveSelectedProject(
                $user,
                (int) $projects->first()->id,
                $leadFilterId,
                $orderFilterId,
            );
        }

        return Inertia::render('Modules/HowMuchFits', [
            'projects' => $projects->map(fn (LoadingPlannerProject $project): array => $this->formatProjectSummary($project, (int) $user->id))->values(),
            'selectedProject' => $selectedProject ? $this->formatProject($selectedProject, (int) $user->id) : null,
            'linkContext' => $linkContext,
            'initialStep' => $request->string('step')->toString() ?: null,
            'viewerCanSeeAllProjects' => LoadingPlannerAccess::canViewAllProjects($user),
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
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $lead = $this->resolveLeadForLink($user, isset($validated['lead_id']) ? (int) $validated['lead_id'] : null);
        $order = $this->resolveOrderForLink($user, isset($validated['order_id']) ? (int) $validated['order_id'] : null);

        if ($lead === null && $order === null && $request->filled('lead')) {
            $lead = $this->resolveLeadForLink($user, $request->integer('lead'));
        }

        if ($lead === null && $order === null && $request->filled('order')) {
            $order = $this->resolveOrderForLink($user, $request->integer('order'));
        }

        $template = TransportTemplate::query()->where('is_active', true)->orderBy('sort_order')->first();

        $project = DB::transaction(function () use ($user, $validated, $lead, $order, $template): LoadingPlannerProject {
            $project = LoadingPlannerProject::query()->create([
                'user_id' => $user->id,
                'lead_id' => $order?->lead_id ?? $lead?->id,
                'order_id' => $order?->id,
                'selected_transport_template_id' => $template?->id,
                'name' => trim((string) ($validated['name'] ?? '')) ?: $this->defaultProjectName($lead, $order),
                'status' => 'draft',
            ]);

            if ($order instanceof Order) {
                $this->cargoSeedService->seedFromOrder($project, $order);
            } elseif ($lead instanceof Lead) {
                $this->cargoSeedService->seedFromLead($project, $lead);
            } else {
                $this->seedDefaultDemoCargo($project);
            }

            return $project;
        });

        return to_route('modules.how-much-fits.index', $this->indexRouteParams($project));
    }

    public function updateProject(Request $request, LoadingPlannerProject $loadingPlannerProject): RedirectResponse
    {
        abort_unless(LoadingPlannerAccess::canMutateProject($request->user(), $loadingPlannerProject), 404);

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
                        'client_key' => $itemData['client_key'] ?? (string) Str::uuid(),
                        'package_type' => $itemData['package_type'] ?? 'box',
                        'quantity' => $itemData['quantity'],
                        'length_mm' => $itemData['length_mm'],
                        'width_mm' => $itemData['width_mm'],
                        'height_mm' => $itemData['height_mm'],
                        'weight_kg' => $itemData['weight_kg'] ?? 0,
                        'can_rotate' => (bool) ($itemData['can_rotate'] ?? true),
                        'stackable' => (bool) ($itemData['stackable'] ?? false),
                        'max_stack' => ($itemData['stackable'] ?? false)
                            ? ($itemData['max_stack'] ?? 5)
                            : ($itemData['max_stack'] ?? 1),
                        'can_tilt' => (bool) ($itemData['can_tilt'] ?? false),
                        'color' => $itemData['color'] ?? ($groupData['color'] ?? '#60a5fa'),
                        'sort_order' => $itemIndex + 1,
                    ]);
                }
            }
        });

        return to_route('modules.how-much-fits.index', $this->indexRouteParams($loadingPlannerProject));
    }

    public function destroyProject(Request $request, LoadingPlannerProject $loadingPlannerProject): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(LoadingPlannerAccess::canMutateProject($user, $loadingPlannerProject), 404);

        $linkParams = $this->indexRouteParamsFromRequest($request);
        $loadingPlannerProject->delete();

        $redirectParams = $linkParams;
        $nextProject = $this->resolveNextVisibleProject($user, $linkParams);
        if ($nextProject instanceof LoadingPlannerProject) {
            $redirectParams['project'] = $nextProject->id;
        }

        return to_route('modules.how-much-fits.index', $redirectParams);
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
        $this->ensureCanMutateTransportTemplate($request, $transportTemplate);

        $transportTemplate->update($request->validate($this->transportTemplateRules()));

        return back();
    }

    public function destroyTransportTemplate(Request $request, TransportTemplate $transportTemplate): RedirectResponse
    {
        $this->ensureCanMutateTransportTemplate($request, $transportTemplate);

        $transportTemplate->delete();

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLinkContext(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        if ($request->filled('order')) {
            $order = Order::query()->find($request->integer('order'));
            if ($order instanceof Order && LoadingPlannerAccess::canAccessOrder($user, $order)) {
                return [
                    'type' => 'order',
                    'order_id' => $order->id,
                    'lead_id' => $order->lead_id,
                    'label' => 'Заказ '.($order->order_number ?? '#'.$order->id),
                    'url' => route('orders.edit', $order),
                ];
            }
        }

        if ($request->filled('lead')) {
            $lead = Lead::query()->find($request->integer('lead'));
            if ($lead instanceof Lead && LoadingPlannerAccess::canAccessLead($user, $lead)) {
                return [
                    'type' => 'lead',
                    'lead_id' => $lead->id,
                    'order_id' => null,
                    'label' => 'Лид #'.($lead->number ?? $lead->id),
                    'url' => route('leads.show', $lead),
                ];
            }
        }

        return null;
    }

    private function resolveLeadForLink(User $user, ?int $leadId): ?Lead
    {
        if ($leadId === null || $leadId <= 0) {
            return null;
        }

        $lead = Lead::query()->find($leadId);
        if (! $lead instanceof Lead || ! LoadingPlannerAccess::canAccessLead($user, $lead)) {
            throw ValidationException::withMessages([
                'lead_id' => 'Нет доступа к выбранному лиду.',
            ]);
        }

        return $lead;
    }

    private function resolveOrderForLink(User $user, ?int $orderId): ?Order
    {
        if ($orderId === null || $orderId <= 0) {
            return null;
        }

        $order = Order::query()->find($orderId);
        if (! $order instanceof Order || ! LoadingPlannerAccess::canAccessOrder($user, $order)) {
            throw ValidationException::withMessages([
                'order_id' => 'Нет доступа к выбранному заказу.',
            ]);
        }

        return $order;
    }

    private function defaultProjectName(?Lead $lead, ?Order $order): string
    {
        if ($order instanceof Order) {
            return 'Загрузка — заказ '.($order->order_number ?? '#'.$order->id);
        }

        if ($lead instanceof Lead) {
            return 'Загрузка — лид #'.($lead->number ?? $lead->id);
        }

        return 'Новый расчёт';
    }

    /**
     * @return array<string, int|string|null>
     */
    private function indexRouteParams(LoadingPlannerProject $project): array
    {
        $params = ['project' => $project->id];

        if ($project->order_id !== null) {
            $params['order'] = $project->order_id;
        } elseif ($project->lead_id !== null) {
            $params['lead'] = $project->lead_id;
        }

        return $params;
    }

    /**
     * @return array<string, int>
     */
    private function indexRouteParamsFromRequest(Request $request): array
    {
        $params = [];

        if ($request->filled('order')) {
            $params['order'] = $request->integer('order');
        } elseif ($request->filled('lead')) {
            $params['lead'] = $request->integer('lead');
        }

        return $params;
    }

    private function resolveSelectedProject(
        User $user,
        int $projectId,
        ?int $leadFilterId,
        ?int $orderFilterId,
    ): ?LoadingPlannerProject {
        if ($projectId <= 0) {
            return null;
        }

        return LoadingPlannerAccess::applyVisibleProjectsScope(
            LoadingPlannerProject::query(),
            $user,
        )
            ->whereKey($projectId)
            ->when($orderFilterId !== null, fn ($query) => $query->where('order_id', $orderFilterId))
            ->when($leadFilterId !== null && $orderFilterId === null, fn ($query) => $query->where('lead_id', $leadFilterId))
            ->with(['cargoGroups.items', 'selectedTransportTemplate', 'user:id,name', 'lead:id,number,title', 'order:id,order_number,lead_id'])
            ->first();
    }

    /**
     * @param  array<string, int>  $linkParams
     */
    private function resolveNextVisibleProject(User $user, array $linkParams): ?LoadingPlannerProject
    {
        return LoadingPlannerAccess::applyVisibleProjectsScope(
            LoadingPlannerProject::query(),
            $user,
        )
            ->when(isset($linkParams['order']), fn ($query) => $query->where('order_id', $linkParams['order']))
            ->when(isset($linkParams['lead']) && ! isset($linkParams['order']), fn ($query) => $query->where('lead_id', $linkParams['lead']))
            ->orderByDesc('updated_at')
            ->first();
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
            'cargo_groups.*.items.*.client_key' => ['nullable', 'string', 'max:80'],
            'cargo_groups.*.items.*.package_type' => ['nullable', Rule::in(['pallet', 'box', 'crate', 'roll', 'bag', 'barrel', 'custom'])],
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

    private function ensureCanMutateTransportTemplate(Request $request, TransportTemplate $transportTemplate): void
    {
        if (! $transportTemplate->is_system) {
            return;
        }

        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);
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
            $group->items()->create([...$item, 'client_key' => (string) Str::uuid(), 'sort_order' => $index + 1]);
        }

        return $project;
    }

    private function seedDefaultDemoCargo(LoadingPlannerProject $project): void
    {
        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        $group->items()->create([
            'client_key' => (string) Str::uuid(),
            'name' => 'Паллета EUR',
            'package_type' => 'pallet',
            'quantity' => 10,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 1200,
            'weight_kg' => 350,
            'can_rotate' => true,
            'stackable' => false,
            'max_stack' => 5,
            'can_tilt' => false,
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);
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
            ['name' => 'Electric Bicycle', 'package_type' => 'box', 'quantity' => 18, 'length_mm' => 1350, 'width_mm' => 270, 'height_mm' => 700, 'weight_kg' => 28, 'can_rotate' => true, 'stackable' => true, 'max_stack' => 5, 'can_tilt' => false, 'color' => '#f9e8c9'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProjectSummary(LoadingPlannerProject $project, ?int $viewerUserId = null): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'transport_name' => $project->selectedTransportTemplate?->name,
            'updated_at' => optional($project->updated_at)->format('d.m.Y'),
            'created_at' => optional($project->created_at)->format('d.m.Y'),
            'owner_name' => $project->user?->name,
            'lead_id' => $project->lead_id,
            'order_id' => $project->order_id,
            'link_label' => $this->projectLinkLabel($project),
            'is_shared' => $viewerUserId !== null && (int) $project->user_id !== $viewerUserId,
        ];
    }

    private function projectLinkLabel(LoadingPlannerProject $project): ?string
    {
        if ($project->order_id !== null) {
            return 'Заказ '.($project->order?->order_number ?? '#'.$project->order_id);
        }

        if ($project->lead_id !== null) {
            return 'Лид #'.($project->lead?->number ?? $project->lead_id);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProject(LoadingPlannerProject $project, ?int $viewerUserId = null): array
    {
        return [
            ...$this->formatProjectSummary($project, $viewerUserId),
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
                    'client_key' => $item->client_key,
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
