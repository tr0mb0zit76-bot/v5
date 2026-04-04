<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillContractorDefaults extends Command
{
    protected $signature = 'legacy:backfill-contractor-defaults {--dry-run : Show changes without writing them}';

    protected $description = 'Backfill contractor default payment settings from existing orders';

    public function handle(): int
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasTable('orders')) {
            $this->error('Required tables `contractors` and `orders` were not found.');

            return self::FAILURE;
        }

        $requiredColumns = [
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'cooperation_terms_notes',
            'debt_limit_currency',
        ];

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('contractors', $column)) {
                $this->error("Column `contractors.{$column}` is missing. Run schema migrations first.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $contractors = DB::table('contractors')
            ->select([
                'id',
                'name',
                'default_customer_payment_form',
                'default_customer_payment_term',
                'default_carrier_payment_form',
                'default_carrier_payment_term',
                'cooperation_terms_notes',
                'debt_limit_currency',
            ])
            ->orderBy('id')
            ->get();

        if ($contractors->isEmpty()) {
            $this->info('No contractors found.');

            return self::SUCCESS;
        }

        $updatedCount = 0;
        $previewCount = 0;

        $bar = $this->output->createProgressBar($contractors->count());
        $bar->start();

        foreach ($contractors as $contractor) {
            $updates = $this->resolveUpdates($contractor);

            if ($updates === []) {
                $bar->advance();

                continue;
            }

            if ($dryRun) {
                $previewCount++;
                $this->newLine();
                $this->line(sprintf(
                    '#%d %s => %s',
                    $contractor->id,
                    $contractor->name,
                    json_encode($updates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                ));
            } else {
                DB::table('contractors')
                    ->where('id', $contractor->id)
                    ->update($updates + ['updated_at' => now()]);

                $updatedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Dry run finished. Contractors with pending updates: {$previewCount}.");
        } else {
            $this->info("Backfill finished. Updated contractors: {$updatedCount}.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  object{id:int,name:string|null,default_customer_payment_form:?string,default_customer_payment_term:?string,default_carrier_payment_form:?string,default_carrier_payment_term:?string,cooperation_terms_notes:?string,debt_limit_currency:?string}  $contractor
     * @return array<string, string>
     */
    private function resolveUpdates(object $contractor): array
    {
        $updates = [];

        $customerOrder = DB::table('orders')
            ->select([
                'customer_payment_form',
                'customer_payment_term',
                'payment_terms',
                'special_notes',
            ])
            ->where('customer_id', $contractor->id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->first();

        $carrierOrder = DB::table('orders')
            ->select([
                'carrier_payment_form',
                'carrier_payment_term',
                'payment_terms',
                'special_notes',
            ])
            ->where('carrier_id', $contractor->id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->first();

        if ($this->isBlank($contractor->default_customer_payment_form) && $this->isFilled($customerOrder?->customer_payment_form ?? null)) {
            $updates['default_customer_payment_form'] = $customerOrder->customer_payment_form;
        }

        if ($this->isBlank($contractor->default_customer_payment_term) && $this->isFilled($customerOrder?->customer_payment_term ?? null)) {
            $updates['default_customer_payment_term'] = $customerOrder->customer_payment_term;
        }

        if ($this->isBlank($contractor->default_carrier_payment_form) && $this->isFilled($carrierOrder?->carrier_payment_form ?? null)) {
            $updates['default_carrier_payment_form'] = $carrierOrder->carrier_payment_form;
        }

        if ($this->isBlank($contractor->default_carrier_payment_term) && $this->isFilled($carrierOrder?->carrier_payment_term ?? null)) {
            $updates['default_carrier_payment_term'] = $carrierOrder->carrier_payment_term;
        }

        if ($this->isBlank($contractor->cooperation_terms_notes)) {
            $note = $this->firstFilled([
                $customerOrder?->payment_terms ?? null,
                $carrierOrder?->payment_terms ?? null,
                $customerOrder?->special_notes ?? null,
                $carrierOrder?->special_notes ?? null,
            ]);

            if ($note !== null) {
                $updates['cooperation_terms_notes'] = $note;
            }
        }

        if ($this->isBlank($contractor->debt_limit_currency)) {
            $updates['debt_limit_currency'] = 'RUB';
        }

        return $updates;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function isFilled(?string $value): bool
    {
        return ! $this->isBlank($value);
    }

    /**
     * @param  array<int, ?string>  $values
     */
    private function firstFilled(array $values): ?string
    {
        return Collection::make($values)
            ->first(fn (?string $value): bool => $this->isFilled($value));
    }
}
