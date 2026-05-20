<?php

namespace App\Console\Commands;

use App\Models\MailThread;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckCommercialOfferMailNudgesCommand extends Command
{
    protected $signature = 'commercial:check-offer-mail-nudges';

    protected $description = 'Создаёт задачи-напоминания, если на исходящее КП нет ответа дольше порога этапа';

    public function handle(): int
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('tasks')) {
            return self::SUCCESS;
        }

        $defaultDays = (int) config('commercial_intelligence.offer_no_reply_nudge_days', 3);
        $created = 0;

        MailThread::query()
            ->whereNotNull('lead_offer_id')
            ->whereNotNull('last_outbound_at')
            ->whereNull('last_inbound_at')
            ->with(['lead.businessProcessStage', 'leadOffer'])
            ->orderBy('id')
            ->chunkById(50, function ($threads) use ($defaultDays, &$created): void {
                foreach ($threads as $thread) {
                    $lead = $thread->lead;

                    if ($lead === null) {
                        continue;
                    }

                    $stage = $lead->businessProcessStage;
                    $nudgeDays = $stage?->no_reply_nudge_days ?? $defaultDays;
                    $dueAt = Carbon::parse($thread->last_outbound_at)->addDays($nudgeDays);

                    if ($dueAt->isFuture()) {
                        continue;
                    }

                    $metaKey = 'commercial_offer_no_reply_nudge';
                    $exists = Task::query()
                        ->where('lead_id', $lead->id)
                        ->where('meta->'.$metaKey, $thread->id)
                        ->where('status', '!=', 'done')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $offerNumber = $thread->leadOffer?->number ?? 'КП';

                    Task::query()->create([
                        'number' => 'T-'.Str::upper(Str::random(8)),
                        'title' => 'Нет ответа на '.$offerNumber,
                        'description' => sprintf(
                            'Исходящее письмо по теме «%s» отправлено %s. Свяжитесь с клиентом.',
                            $thread->subject,
                            $thread->last_outbound_at?->format('d.m.Y H:i'),
                        ),
                        'status' => 'new',
                        'priority' => 'high',
                        'due_at' => now()->addDay(),
                        'responsible_id' => $lead->responsible_id,
                        'lead_id' => $lead->id,
                        'created_by' => null,
                        'meta' => [$metaKey => $thread->id],
                    ]);

                    $created++;
                }
            });

        $this->info("Создано задач-напоминаний: {$created}");

        return self::SUCCESS;
    }
}
