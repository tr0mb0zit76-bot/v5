<?php

namespace App\Services\Leads;

use App\Models\ActivityEvent;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\LeadBusinessProcessService;
use App\Support\ActivityEventType;
use App\Support\LeadDataChecks;
use App\Support\LeadGapCatalog;
use App\Support\LeadStageRequirements;
use App\Support\LeadStatus;
use App\Support\LeadViewAuthorization;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class LeadOperationalBriefService
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildForUser(User $user, int $leadId): array
    {
        $lead = Lead::query()->findOrFail($leadId);

        if (! $this->userCanAccessLead($user, $lead)) {
            throw new AuthorizationException('Нет доступа к лиду.');
        }

        return $this->build($lead);
    }

    /**
     * @param  list<int>  $leadIds
     * @return list<array<string, mixed>>
     */
    public function buildManyForUser(User $user, array $leadIds, int $limit = 25): array
    {
        $leadIds = array_values(array_unique(array_filter($leadIds, fn (int $id): bool => $id > 0)));

        if ($leadIds === []) {
            return [];
        }

        $leads = Lead::query()
            ->whereIn('id', array_slice($leadIds, 0, $limit))
            ->get();

        $briefs = [];

        foreach ($leads as $lead) {
            if (! $this->userCanAccessLead($user, $lead)) {
                continue;
            }

            $briefs[] = $this->build($lead);
        }

        return $briefs;
    }

    private function userCanAccessLead(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads')) {
            return false;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Lead $lead): array
    {
        $lead->loadMissing([
            'businessProcess:id,name,slug',
            'businessProcessStage:id,name,is_terminal',
            'routePoints',
            'cargoItems',
            'offers:id,lead_id',
            'tasks:id,lead_id,status',
        ]);

        $checks = LeadDataChecks::run($lead);
        $processSlug = $lead->businessProcess?->slug;
        $stageName = $lead->businessProcessStage?->name;
        $requirements = LeadStageRequirements::forLead($lead->status, $processSlug, $stageName);

        $gaps = $this->resolveGaps($checks, $requirements);
        $positives = $this->resolvePositives($checks, $requirements);
        $risks = $this->resolveRisks($lead);
        $health = $this->resolveHealth($lead, $gaps, $risks);
        $healthScore = $this->healthScore($gaps, $risks);
        $actionsNow = $this->actionsNow($gaps, $risks);
        $daysOnStage = $this->daysOnStage($lead);
        $stageOverdue = $this->leadBusinessProcessService->isStageOverdue($lead);

        $context = [
            'status' => $lead->status,
            'status_label' => LeadStatus::label($lead->status),
            'process_name' => $lead->businessProcess?->name,
            'bp_stage_name' => $stageName,
            'bp_stage_goal' => $lead->businessProcessStage?->stage_goal,
            'days_on_stage' => $daysOnStage,
            'stage_overdue' => $stageOverdue,
        ];

        return [
            'lead_id' => $lead->id,
            'lead_number' => $lead->number,
            'title' => $lead->title,
            'context' => $context,
            'health' => $health,
            'health_score' => $healthScore,
            'summary_ru' => $this->summaryRu($lead, $context, $gaps, $risks, $health),
            'positives' => $positives,
            'gaps' => $gaps,
            'actions_now' => $actionsNow,
            'risks' => $risks,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, bool>  $checks
     * @param  array{blocking: list<string>, recommended: list<string>}  $requirements
     * @return list<array{code: string, label: string, tab: string, severity: string, kind?: string}>
     */
    private function resolveGaps(array $checks, array $requirements): array
    {
        $checkToGap = LeadGapCatalog::checkToGap();
        $gaps = [];

        foreach (['blocking' => 'blocking', 'recommended' => 'recommended'] as $bucket => $severity) {
            foreach ($requirements[$bucket] as $check) {
                if (($checks[$check] ?? false) === true) {
                    continue;
                }

                $code = $checkToGap[$check] ?? $check;
                $meta = LeadGapCatalog::gapMeta($code);

                if ($meta === null) {
                    continue;
                }

                $gaps[] = [
                    'code' => $code,
                    'label' => $meta['label'],
                    'tab' => $meta['tab'],
                    'severity' => $severity,
                    ...array_filter([
                        'kind' => $meta['kind'] ?? null,
                    ]),
                ];
            }
        }

        return $gaps;
    }

    /**
     * @param  array<string, bool>  $checks
     * @param  array{blocking: list<string>, recommended: list<string>}  $requirements
     * @return list<array{code: string, label: string}>
     */
    private function resolvePositives(array $checks, array $requirements): array
    {
        $relevant = array_unique(array_merge($requirements['blocking'], $requirements['recommended']));
        $labels = LeadGapCatalog::positiveLabels();
        $positives = [];

        foreach ($relevant as $check) {
            if (($checks[$check] ?? false) !== true) {
                continue;
            }

            $label = $labels[$check] ?? null;

            if ($label === null) {
                continue;
            }

            $positives[] = [
                'code' => $check,
                'label' => $label,
            ];
        }

        return $positives;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function resolveRisks(Lead $lead): array
    {
        if (LeadStatus::isClosed($lead->status)) {
            return [];
        }

        $risks = [];

        if ($this->leadBusinessProcessService->isStageOverdue($lead)) {
            $risks[] = [
                'code' => 'stage_overdue',
                'label' => 'Этап просрочен по SLA',
            ];
        }

        $idle = $this->currentStageIdleRisk($lead);

        if ($idle !== null) {
            $risks[] = $idle;
        }

        return $risks;
    }

    /**
     * @return array{code: string, label: string}|null
     */
    private function currentStageIdleRisk(Lead $lead): ?array
    {
        if ($lead->stage_entered_at === null || LeadStatus::isClosed($lead->status)) {
            return null;
        }

        $minDays = (float) config('outcome_intelligence.idle_dwell_min_days', 2);
        $maxActivity = (int) config('outcome_intelligence.idle_dwell_max_activity_events', 1);
        $dwellDays = round($lead->stage_entered_at->diffInMinutes(now()) / 1440, 1);

        if ($dwellDays < $minDays) {
            return null;
        }

        $activityCount = $this->activityCountSince($lead, $lead->stage_entered_at);

        if ($activityCount > $maxActivity) {
            return null;
        }

        return [
            'code' => 'idle_dwell',
            'label' => sprintf('%.0f дн. на этапе без активности', $dwellDays),
        ];
    }

    private function activityCountSince(Lead $lead, CarbonImmutable|Carbon $from): int
    {
        $count = 0;

        if ($this->activityLedger->tablesReady()) {
            $count += ActivityEvent::query()
                ->where('subject_type', $lead->getMorphClass())
                ->where('subject_id', $lead->id)
                ->where('event_type', '!=', ActivityEventType::ProcessStageChanged->value)
                ->where('occurred_at', '>=', $from)
                ->count();
        }

        if (Schema::hasTable('lead_activities')) {
            $count += LeadActivity::query()
                ->where('lead_id', $lead->id)
                ->where('type', '!=', 'status_change')
                ->where('created_at', '>=', $from)
                ->count();
        }

        if (Schema::hasTable('tasks')) {
            $count += Task::query()
                ->where('lead_id', $lead->id)
                ->where('created_at', '>=', $from)
                ->count();
        }

        return $count;
    }

    /**
     * @param  list<array{code: string, label: string, tab: string, severity: string, kind?: string}>  $gaps
     * @param  list<array{code: string, label: string}>  $risks
     */
    private function resolveHealth(Lead $lead, array $gaps, array $risks): string
    {
        if (LeadStatus::isClosed($lead->status)) {
            return 'terminal';
        }

        $hasBlocking = collect($gaps)->contains(fn (array $gap): bool => $gap['severity'] === 'blocking');

        if ($hasBlocking || $risks !== []) {
            return 'stuck';
        }

        $hasRecommended = collect($gaps)->contains(fn (array $gap): bool => $gap['severity'] === 'recommended');

        if (! $hasRecommended) {
            return 'ready_to_advance';
        }

        return 'on_track';
    }

    /**
     * @param  list<array{code: string, label: string, tab: string, severity: string, kind?: string}>  $gaps
     * @param  list<array{code: string, label: string}>  $risks
     */
    private function healthScore(array $gaps, array $risks): int
    {
        $deduction = 0;

        foreach ($gaps as $gap) {
            $deduction += $gap['severity'] === 'blocking' ? 25 : 10;
        }

        foreach ($risks as $risk) {
            $deduction += match ($risk['code']) {
                'stage_overdue' => 20,
                'idle_dwell' => 15,
                default => 10,
            };
        }

        return max(0, 100 - min(100, $deduction));
    }

    /**
     * @param  list<array{code: string, label: string, tab: string, severity: string, kind?: string}>  $gaps
     * @param  list<array{code: string, label: string}>  $risks
     * @return list<array{priority: int, label: string, tab?: string, kind?: string, code: string}>
     */
    private function actionsNow(array $gaps, array $risks): array
    {
        $actions = [];
        $priority = 1;

        foreach ($gaps as $gap) {
            if ($gap['severity'] !== 'blocking') {
                continue;
            }

            $actions[] = [
                'priority' => $priority++,
                'code' => $gap['code'],
                'label' => $gap['label'],
                'tab' => $gap['tab'],
                ...array_filter([
                    'kind' => $gap['kind'] ?? null,
                ]),
            ];

            if (count($actions) >= 3) {
                return $actions;
            }
        }

        foreach ($gaps as $gap) {
            if ($gap['severity'] !== 'recommended') {
                continue;
            }

            $actions[] = [
                'priority' => $priority++,
                'code' => $gap['code'],
                'label' => $gap['label'],
                'tab' => $gap['tab'],
                ...array_filter([
                    'kind' => $gap['kind'] ?? null,
                ]),
            ];

            if (count($actions) >= 3) {
                return $actions;
            }
        }

        foreach ($risks as $risk) {
            $actions[] = [
                'priority' => $priority++,
                'code' => $risk['code'],
                'label' => $risk['label'],
                'kind' => 'risk',
            ];

            if (count($actions) >= 3) {
                return $actions;
            }
        }

        return $actions;
    }

    private function daysOnStage(Lead $lead): ?float
    {
        if ($lead->stage_entered_at === null) {
            return null;
        }

        return round($lead->stage_entered_at->diffInMinutes(now()) / 1440, 1);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array{code: string, label: string, tab: string, severity: string}>  $gaps
     * @param  list<array{code: string, label: string}>  $risks
     */
    private function summaryRu(Lead $lead, array $context, array $gaps, array $risks, string $health): string
    {
        if ($health === 'terminal') {
            return sprintf('Лид %s закрыт.', $lead->number);
        }

        if ($health === 'ready_to_advance') {
            $stage = $context['bp_stage_name'] ?? $context['status_label'];

            return sprintf('Лид %s: по этапу «%s» данные собраны — можно переходить дальше.', $lead->number, $stage);
        }

        $stage = $context['bp_stage_name'] ?? $context['status_label'];
        $blocking = collect($gaps)->where('severity', 'blocking')->pluck('label')->take(2)->all();

        if ($blocking !== []) {
            $items = implode(', ', $blocking);
            $suffix = count($gaps) > count($blocking) ? ' и ещё' : '';

            return sprintf('Лид %s на «%s»: сейчас — %s%s.', $lead->number, $stage, mb_strtolower($items), $suffix);
        }

        if ($risks !== []) {
            return sprintf('Лид %s на «%s»: %s.', $lead->number, $stage, mb_strtolower($risks[0]['label']));
        }

        if ($gaps !== []) {
            return sprintf('Лид %s на «%s»: %s.', $lead->number, $stage, mb_strtolower($gaps[0]['label']));
        }

        return sprintf('Лид %s на этапе «%s» — в работе.', $lead->number, $stage);
    }
}
