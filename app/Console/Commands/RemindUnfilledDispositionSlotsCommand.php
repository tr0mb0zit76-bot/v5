<?php

namespace App\Console\Commands;

use App\Services\Disposition\DispositionReminderService;
use App\Support\DispositionSlot;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RemindUnfilledDispositionSlotsCommand extends Command
{
    protected $signature = 'disposition:remind-unfilled-slots {slot : morning|evening}';

    protected $description = 'Создаёт задачи менеджерам по незаполненным слотам диспозиции за сегодня';

    public function handle(DispositionReminderService $reminders): int
    {
        $slot = DispositionSlot::tryFrom((string) $this->argument('slot'));

        if ($slot === null) {
            throw new InvalidArgumentException('Слот должен быть morning или evening.');
        }

        $created = $reminders->createRemindersForSlot($slot);

        $this->info(sprintf('Создано задач по слоту «%s»: %d', $slot->label(), $created));

        return self::SUCCESS;
    }
}
