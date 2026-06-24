<?php

namespace App\Services\Commercial;

use App\Models\ActivityEvent;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\MailThread;
use App\Services\LeadBusinessProcessService;
use App\Support\CommercialNudgeStageConfig;
use App\Support\CommercialNudgeType;
use App\Support\LeadStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class CommercialNudgeEvaluator
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
    ) {}

    /**
     * @return list<CommercialNudgeMatch>
     */
    public function matchesForLead(Lead $lead): array
    {
        if ($this->shouldSkipLead($lead)) {
            return [];
        }

        $lead->loadMissing(['businessProcessStage', 'offers']);

        $stage = $lead->businessProcessStage;
        $matches = [];

        if ($stage !== null) {
            $matches = array_merge(
                $matches,
                $this->stageOverdueMatches($lead, $stage),
                $this->ledgerIdleMatches($lead, $stage),
            );
        }

        return array_merge(
            $matches,
            $this->noReplyMatches($lead, $stage),
            $this->nextContactMissedMatches($lead, $stage),
        );
    }

    /**
     * @return list<CommercialNudgeMatch>
     */
    public function collectAll(): array
    {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        $matches = [];

        Lead::query()
            ->with(['businessProcessStage'])
            ->whereNotIn('status', ['won', 'lost'])
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (&$matches): void {
                foreach ($leads as $lead) {
                    foreach ($this->matchesForLead($lead) as $match) {
                        $matches[] = $match;
                    }
                }
            });

        return $matches;
    }

    private function shouldSkipLead(Lead $lead): bool
    {
        if (LeadStatus::isClosed((string) $lead->status) || (string) $lead->status === 'on_hold') {
            return true;
        }

        if ($lead->responsible_id === null) {
            return true;
        }

        $lead->loadMissing('businessProcessStage');

        if ($lead->businessProcessStage?->is_terminal) {
            return true;
        }

        return false;
    }

    /**
     * @return list<CommercialNudgeMatch>
     */
    private function noReplyMatches(Lead $lead, ?BusinessProcessStage $stage): array
    {
        if (! Schema::hasTable('mail_threads')) {
            return [];
        }

        if ($stage !== null && ! CommercialNudgeStageConfig::isEnabled($stage, CommercialNudgeType::NoReply)) {
            return [];
        }

        if ($stage === null && ! in_array(CommercialNudgeType::NoReply->value, config('commercial_nudges.default_triggers', []), true)) {
            return [];
        }

        $nudgeDays = $stage !== null
            ? CommercialNudgeStageConfig::noReplyDays($stage)
            : max(1, (int) config('commercial_nudges.default_no_reply_days', 3));
        $matches = [];

        MailThread::query()
            ->where('lead_id', $lead->id)
            ->whereNotNull('lead_offer_id')
            ->whereNotNull('last_outbound_at')
            ->whereNull('last_inbound_at')
            ->with('leadOffer')
            ->orderBy('id')
            ->get()
            ->each(function (MailThread $thread) use ($lead, $nudgeDays, &$matches): void {
                $dueAt = Carbon::parse($thread->last_outbound_at)->addDays($nudgeDays);

                if ($dueAt->isFuture()) {
                    return;
                }

                $offerNumber = $thread->leadOffer?->number ?? 'КП';

                $matches[] = new CommercialNudgeMatch(
                    type: CommercialNudgeType::NoReply,
                    lead: $lead,
                    subjectId: (string) $thread->id,
                    title: 'Нет ответа на '.$offerNumber,
                    description: sprintf(
                        'Исходящее письмо по теме «%s» отправлено %s. Свяжитесь с клиентом.',
                        $thread->subject,
                        $thread->last_outbound_at?->format('d.m.Y H:i'),
                    ),
                    priority: CommercialNudgeType::NoReply->defaultPriority(),
                );
            });

        return $matches;
    }

    /**
     * @return list<CommercialNudgeMatch>
     */
    private function stageOverdueMatches(Lead $lead, BusinessProcessStage $stage): array
    {
        if (! CommercialNudgeStageConfig::isEnabled($stage, CommercialNudgeType::StageOverdue)) {
            return [];
        }

        if (! $this->leadBusinessProcessService->isStageOverdue($lead)) {
            return [];
        }

        return [
            new CommercialNudgeMatch(
                type: CommercialNudgeType::StageOverdue,
                lead: $lead,
                subjectId: (string) $stage->id,
                title: 'Просрочен этап «'.$stage->name.'»',
                description: sprintf(
                    'Лид %s на этапе «%s» дольше норматива. Срок этапа: %s.',
                    $lead->number,
                    $stage->name,
                    optional($lead->stage_due_at)?->format('d.m.Y H:i') ?? 'не задан',
                ),
                priority: CommercialNudgeType::StageOverdue->defaultPriority(),
            ),
        ];
    }

    /**
     * @return list<CommercialNudgeMatch>
     */
    private function nextContactMissedMatches(Lead $lead, ?BusinessProcessStage $stage): array
    {
        if ($lead->next_contact_at === null) {
            return [];
        }

        if ($stage !== null && ! CommercialNudgeStageConfig::isEnabled($stage, CommercialNudgeType::NextContactMissed)) {
            return [];
        }

        if ($lead->next_contact_at->isFuture()) {
            return [];
        }

        return [
            new CommercialNudgeMatch(
                type: CommercialNudgeType::NextContactMissed,
                lead: $lead,
                subjectId: 'next_contact',
                title: 'Пропущен следующий контакт',
                description: sprintf(
                    'Запланированный контакт по лиду %s был на %s. Свяжитесь с клиентом.',
                    $lead->number,
                    $lead->next_contact_at->format('d.m.Y H:i'),
                ),
                priority: CommercialNudgeType::NextContactMissed->defaultPriority(),
            ),
        ];
    }

    /**
     * @return list<CommercialNudgeMatch>
     */
    private function ledgerIdleMatches(Lead $lead, BusinessProcessStage $stage): array
    {
        if (! Schema::hasTable('activity_events')) {
            return [];
        }

        if (! CommercialNudgeStageConfig::isEnabled($stage, CommercialNudgeType::LedgerIdle)) {
            return [];
        }

        $idleDays = CommercialNudgeStageConfig::ledgerIdleDays($stage);

        if ($idleDays === null) {
            return [];
        }

        $anchor = $lead->stage_entered_at ?? $lead->process_started_at ?? $lead->created_at;

        if ($anchor === null) {
            return [];
        }

        $lastOccurredAt = ActivityEvent::query()
            ->where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->max('occurred_at');

        $reference = $lastOccurredAt !== null
            ? Carbon::parse($lastOccurredAt)
            : Carbon::parse($anchor);

        if ($reference->greaterThan(Carbon::parse($anchor))) {
            $idleSince = $reference;
        } else {
            $idleSince = Carbon::parse($anchor);
        }

        $dueAt = $idleSince->copy()->addDays($idleDays);

        if ($dueAt->isFuture()) {
            return [];
        }

        return [
            new CommercialNudgeMatch(
                type: CommercialNudgeType::LedgerIdle,
                lead: $lead,
                subjectId: (string) $stage->id,
                title: 'Нет активности по лиду',
                description: sprintf(
                    'На этапе «%s» нет событий в ленте с %s (порог %d дн.). Зафиксируйте контакт или продвиньте этап.',
                    $stage->name,
                    $idleSince->format('d.m.Y H:i'),
                    $idleDays,
                ),
                priority: CommercialNudgeType::LedgerIdle->defaultPriority(),
            ),
        ];
    }
}
