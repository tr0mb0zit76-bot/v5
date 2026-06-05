<?php

namespace App\Console\Commands;

use App\Models\MailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PurgeMailMessageBodiesCommand extends Command
{
    protected $signature = 'mail:purge-non-important-bodies {--months= : Порог в месяцах (по умолчанию из config)}';

    protected $description = 'Сохраняет краткий контекст и очищает тело неважных писем старше порога';

    public function handle(): int
    {
        if (! Schema::hasTable('mail_messages')) {
            $this->warn('Таблица mail_messages отсутствует.');

            return self::SUCCESS;
        }

        $months = (int) ($this->option('months')
            ?: config('commercial_intelligence.mail_retention.purge_older_than_months', 6));
        $cutoff = Carbon::now()->subMonths($months);
        $maxChars = (int) config('commercial_intelligence.mail_retention.summary_max_chars', 500);

        $query = MailMessage::query()
            ->where('is_important', false)
            ->whereNull('content_purged_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('sent_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff): void {
                        $q2->whereNull('sent_at')->where('created_at', '<', $cutoff);
                    });
            });

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($messages) use (&$count, $maxChars): void {
            foreach ($messages as $message) {
                $body = MailMessageBodyPresenter::plainText($message) ?? '';

                $message->forceFill([
                    'retention_summary' => $body !== ''
                        ? Str::limit($body, $maxChars)
                        : ($message->retention_summary ?: '—'),
                    'body_text' => null,
                    'body_html' => null,
                    'content_purged_at' => now(),
                ])->save();

                $count++;
            }
        });

        $this->info("Обработано сообщений: {$count} (старше {$months} мес., не «важно»).");

        return self::SUCCESS;
    }
}
