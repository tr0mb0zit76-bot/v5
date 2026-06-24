<?php

namespace App\Http\Controllers;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\SalesScript;
use App\Services\BusinessProcessAnalyticsService;
use App\Services\LeadBusinessProcessService;
use App\Services\Reports\LeadProcessReportsService;
use App\Support\BusinessProcessPlaybook;
use App\Support\CommercialNudgeType;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsBusinessProcessController extends Controller
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
        private readonly BusinessProcessAnalyticsService $businessProcessAnalyticsService,
        private readonly LeadProcessReportsService $leadProcessReportsService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless($this->leadBusinessProcessService->tablesReady(), 404);

        $lookbackDays = max(7, min(365, (int) $request->query('lookback_days', 90)));

        $processes = BusinessProcess::query()
            ->with(['stages' => fn ($query) => $query->with('salesScript:id,title')->orderBy('sequence')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BusinessProcess $process): array => $this->serializeProcess($process))
            ->values()
            ->all();

        return Inertia::render('Settings/BusinessProcesses/Index', [
            'processes' => $processes,
            'playbook_placeholders' => BusinessProcessPlaybook::placeholderCatalog(),
            'playbook_templates' => [
                'stage' => BusinessProcessPlaybook::emptyPlaybookTemplate('Название этапа'),
                'success_criteria' => BusinessProcessPlaybook::emptySuccessCriteriaTemplate(),
            ],
            'sales_script_options' => $this->salesScriptOptions(),
            'nudge_type_options' => $this->nudgeTypeOptions(),
            'health' => $this->businessProcessAnalyticsService->healthOverview($lookbackDays),
            'stage_issues' => $this->leadProcessReportsService->processStageIssues(),
            'lookback_days' => $lookbackDays,
        ]);
    }

    public function storeProcess(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless($this->leadBusinessProcessService->tablesReady(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $this->leadBusinessProcessService->makeSlug($validated['name']);

        BusinessProcess::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => BusinessProcessPlaybook::normalize($validated['description'] ?? null),
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back();
    }

    public function updateProcess(Request $request, BusinessProcess $businessProcess): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $businessProcess->update([
            'name' => $validated['name'],
            'slug' => $this->leadBusinessProcessService->makeSlug($validated['name'], $businessProcess->id),
            'description' => BusinessProcessPlaybook::normalize($validated['description'] ?? null),
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back();
    }

    public function destroyProcess(Request $request, BusinessProcess $businessProcess): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        if (Schema::hasColumn('leads', 'business_process_id')) {
            $inUse = $businessProcess->leads()->exists();
            abort_if($inUse, 422, 'Процесс используется в лидах и не может быть удалён.');
        }

        $businessProcess->delete();

        return back();
    }

    public function storeStage(Request $request, BusinessProcess $businessProcess): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $this->validateStage($request);

        $sequence = (int) ($validated['sequence'] ?? 0);
        if ($sequence <= 0) {
            $sequence = ((int) $businessProcess->stages()->max('sequence')) + 10;
        }

        $businessProcess->stages()->create([
            ...$validated,
            'sequence' => $sequence,
        ]);

        return back();
    }

    public function updateStage(Request $request, BusinessProcess $businessProcess, BusinessProcessStage $stage): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless((int) $stage->business_process_id === (int) $businessProcess->id, 404);

        $validated = $this->validateStage($request);

        $stage->update($validated);

        return back();
    }

    public function destroyStage(Request $request, BusinessProcess $businessProcess, BusinessProcessStage $stage): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless((int) $stage->business_process_id === (int) $businessProcess->id, 404);

        if (Schema::hasColumn('leads', 'business_process_stage_id')) {
            abort_if($stage->leadsOnStage()->exists(), 422, 'Этап используется в лидах и не может быть удалён.');
        }

        $stage->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcess(BusinessProcess $process): array
    {
        return [
            'id' => $process->id,
            'name' => $process->name,
            'slug' => $process->slug,
            'description' => $process->description,
            'is_active' => $process->is_active,
            'sort_order' => $process->sort_order,
            'stages' => $process->stages->map(fn (BusinessProcessStage $stage): array => [
                'id' => $stage->id,
                'name' => $stage->name,
                'description' => $stage->description,
                'stage_goal' => $stage->stage_goal,
                'success_criteria' => $stage->success_criteria,
                'sales_script_id' => $stage->sales_script_id,
                'sales_script_title' => $stage->salesScript?->title,
                'sequence' => $stage->sequence,
                'duration_days' => $stage->duration_days,
                'is_terminal' => $stage->is_terminal,
                'terminal_outcome' => $stage->terminal_outcome,
                'auto_create_task' => (bool) ($stage->auto_create_task ?? false),
                'task_title_template' => $stage->task_title_template,
                'task_description_template' => $stage->task_description_template,
                'task_due_days_offset' => (int) ($stage->task_due_days_offset ?? 0),
                'task_priority' => $stage->task_priority ?? 'medium',
                'no_reply_nudge_days' => $stage->no_reply_nudge_days,
                'nudge_triggers' => $stage->nudge_triggers ?? [],
                'ledger_idle_nudge_days' => $stage->ledger_idle_nudge_days,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stage_goal' => ['nullable', 'string', 'max:500'],
            'success_criteria' => ['nullable', 'string'],
            'sales_script_id' => ['nullable', 'integer', 'exists:sales_scripts,id'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_terminal' => ['required', 'boolean'],
            'terminal_outcome' => ['nullable', Rule::in(['won', 'lost', 'neutral'])],
            'auto_create_task' => ['nullable', 'boolean'],
            'task_title_template' => ['nullable', 'string', 'max:255'],
            'task_description_template' => ['nullable', 'string'],
            'task_due_days_offset' => ['nullable', 'integer', 'min:0', 'max:365'],
            'task_priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'no_reply_nudge_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'nudge_triggers' => ['nullable', 'array'],
            'nudge_triggers.*' => ['string', Rule::in(CommercialNudgeType::values())],
            'ledger_idle_nudge_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $validated['description'] = BusinessProcessPlaybook::normalize($validated['description'] ?? null);
        $validated['success_criteria'] = BusinessProcessPlaybook::normalize($validated['success_criteria'] ?? null);
        $validated['task_description_template'] = BusinessProcessPlaybook::normalize($validated['task_description_template'] ?? null);
        $validated['stage_goal'] = filled($validated['stage_goal'] ?? null)
            ? trim((string) $validated['stage_goal'])
            : null;
        $validated['sales_script_id'] = filled($validated['sales_script_id'] ?? null)
            ? (int) $validated['sales_script_id']
            : null;

        if (Schema::hasColumn('business_process_stages', 'auto_create_task')) {
            $validated['auto_create_task'] = (bool) ($validated['auto_create_task'] ?? false);
            $validated['task_due_days_offset'] = (int) ($validated['task_due_days_offset'] ?? 0);
            $validated['task_priority'] = $validated['task_priority'] ?? 'medium';
        }

        if (Schema::hasColumn('business_process_stages', 'no_reply_nudge_days')) {
            $validated['no_reply_nudge_days'] = filled($validated['no_reply_nudge_days'] ?? null)
                ? (int) $validated['no_reply_nudge_days']
                : null;
        }

        if (Schema::hasColumn('business_process_stages', 'nudge_triggers')) {
            $triggers = $validated['nudge_triggers'] ?? null;
            $validated['nudge_triggers'] = is_array($triggers) && $triggers !== []
                ? array_values(array_unique(array_map('strval', $triggers)))
                : null;
        }

        if (Schema::hasColumn('business_process_stages', 'ledger_idle_nudge_days')) {
            $validated['ledger_idle_nudge_days'] = filled($validated['ledger_idle_nudge_days'] ?? null)
                ? (int) $validated['ledger_idle_nudge_days']
                : null;
        }

        return $validated;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function nudgeTypeOptions(): array
    {
        $types = config('commercial_nudges.types', []);

        return collect(CommercialNudgeType::cases())
            ->map(fn (CommercialNudgeType $type): array => [
                'value' => $type->value,
                'label' => (string) ($types[$type->value]['label'] ?? $type->label()),
                'description' => (string) ($types[$type->value]['description'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function salesScriptOptions(): array
    {
        if (! Schema::hasTable('sales_scripts')) {
            return [];
        }

        return SalesScript::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (SalesScript $script): array => [
                'id' => $script->id,
                'title' => $script->title,
            ])
            ->values()
            ->all();
    }
}
