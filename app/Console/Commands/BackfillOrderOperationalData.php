<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class BackfillOrderOperationalData extends Command
{
    protected $signature = 'legacy:backfill-order-operations {--dry-run : Show changes without writing them}';

    protected $description = 'Backfill financial terms and order document workflow data from legacy order records';

    public function handle(): int
    {
        if (! Schema::hasTable('orders')) {
            $this->error('Required table `orders` was not found.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('financial_terms')) {
            $this->error('Table `financial_terms` is missing. Run schema migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $orders = DB::table('orders')
            ->select([
                'id',
                'order_date',
                'customer_rate',
                'customer_payment_form',
                'customer_payment_term',
                'payment_terms',
                'carrier_rate',
                'carrier_payment_form',
                'carrier_payment_term',
                'performers',
                'kpi_percent',
                'delta',
            ])
            ->orderBy('id')
            ->get();

        $orderUpdates = 0;
        $documentUpdates = 0;
        $previewRows = 0;

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            $financialPayload = $this->financialPayload($order);

            if ($financialPayload !== null) {
                if ($dryRun) {
                    $previewRows++;
                    $this->newLine();
                    $this->line(sprintf(
                        'order #%d financial_terms => %s',
                        $order->id,
                        json_encode($financialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    ));
                } else {
                    DB::table('financial_terms')->updateOrInsert(
                        ['order_id' => $order->id],
                        $financialPayload
                    );
                    $orderUpdates++;
                }
            }

            if (Schema::hasTable('order_documents')) {
                $documents = DB::table('order_documents')
                    ->where('order_id', $order->id)
                    ->get();

                foreach ($documents as $document) {
                    $documentPayload = $this->documentPayload($document);

                    if ($documentPayload === []) {
                        continue;
                    }

                    if ($dryRun) {
                        $previewRows++;
                        $this->newLine();
                        $this->line(sprintf(
                            'order_document #%d => %s',
                            $document->id,
                            json_encode($documentPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                        ));
                    } else {
                        DB::table('order_documents')
                            ->where('id', $document->id)
                            ->update($documentPayload + ['updated_at' => now()]);
                        $documentUpdates++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Dry run finished. Preview rows: {$previewRows}.");
        } else {
            $this->info("Backfill finished. financial_terms upserts: {$orderUpdates}, order_documents updates: {$documentUpdates}.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  object{id:int,order_date:?string,customer_rate:mixed,customer_payment_form:?string,customer_payment_term:?string,payment_terms:?string,carrier_rate:mixed,carrier_payment_form:?string,carrier_payment_term:?string,performers:mixed,kpi_percent:mixed,delta:mixed}  $order
     * @return array<string, mixed>|null
     */
    private function financialPayload(object $order): ?array
    {
        $existing = DB::table('financial_terms')->where('order_id', $order->id)->first();

        $decodedPaymentTerms = $this->decodeJsonObject($order->payment_terms);
        $existingCosts = is_array($existing?->contractors_costs ?? null)
            ? $existing->contractors_costs
            : $this->decodeJsonArray($existing?->contractors_costs ?? null);
        $performers = $this->decodeJsonArray($order->performers);

        $contractorsCosts = $existingCosts;

        if ($contractorsCosts === []) {
            if ($performers !== []) {
                $contractorsCosts = array_map(
                    fn (array $performer, int $index): array => [
                        'stage' => $performer['stage'] ?? 'leg_'.($index + 1),
                        'contractor_id' => $performer['contractor_id'] ?? null,
                        'amount' => $index === 0 ? $this->decimalOrNull($order->carrier_rate) : null,
                        'currency' => 'RUB',
                        'payment_form' => $order->carrier_payment_form ?: 'no_vat',
                        'payment_schedule' => [],
                    ],
                    $performers,
                    array_keys($performers)
                );
            } elseif ($this->decimalOrNull($order->carrier_rate) !== null) {
                $contractorsCosts = [[
                    'stage' => 'leg_1',
                    'contractor_id' => null,
                    'amount' => $this->decimalOrNull($order->carrier_rate),
                    'currency' => 'RUB',
                    'payment_form' => $order->carrier_payment_form ?: 'no_vat',
                    'payment_schedule' => [],
                ]];
            }
        }

        $clientPaymentTerms = $existing?->client_payment_terms
            ?: ($decodedPaymentTerms['client']['payment_schedule']['postpayment_days'] ?? null
                ? $this->buildScheduleSummary($decodedPaymentTerms['client']['payment_schedule'])
                : $order->customer_payment_term);

        $totalCost = $existing?->total_cost ?? collect($contractorsCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $margin = $existing?->margin ?? $this->decimalOrZero($order->delta);

        if (
            $existing !== null
            && $existing->client_price !== null
            && $existing->client_currency !== null
            && $existing->client_payment_terms !== null
            && $existingCosts !== []
        ) {
            return null;
        }

        return [
            'client_price' => $existing?->client_price ?? $this->decimalOrNull($order->customer_rate),
            'client_currency' => $existing?->client_currency ?? 'RUB',
            'client_payment_terms' => $clientPaymentTerms,
            'contractors_costs' => json_encode($contractorsCosts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'total_cost' => $totalCost,
            'margin' => $margin,
            'additional_costs' => $existing?->additional_costs ?? json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $existing?->created_at ?? now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  object{id:int,type:string,generated_pdf_path:?string,template_id:mixed,status:?string,signature_status:?string,requires_counterparty_signature:mixed,internal_signed_at:mixed,internal_signed_file_path:?string,counterparty_signed_at:mixed,counterparty_signed_file_path:?string,signed_at:mixed,signed_by:mixed,file_path:?string}  $document
     * @return array<string, mixed>
     */
    private function documentPayload(object $document): array
    {
        $payload = [];

        if ($document->document_group === null ?? true) {
            $payload['document_group'] = $this->documentGroupForType($document->type);
        }

        if (($document->source ?? null) === null) {
            $payload['source'] = ($document->generated_pdf_path !== null || $document->template_id !== null) ? 'generated' : 'uploaded';
        }

        $requiresCounterpartySignature = (bool) ($document->requires_counterparty_signature ?? false);
        if (($document->requires_counterparty_signature ?? null) === null || $document->requires_counterparty_signature === 0) {
            $payload['requires_counterparty_signature'] = $this->requiresCounterpartySignature($document->type);
            $requiresCounterpartySignature = $payload['requires_counterparty_signature'];
        }

        if (($document->signature_status ?? null) === null) {
            $payload['signature_status'] = $this->resolveSignatureStatus($document, $requiresCounterpartySignature);
        }

        return $payload;
    }

    private function documentGroupForType(string $type): string
    {
        return match ($type) {
            'request' => 'request',
            'waybill', 'cmr', 'packing_list', 'customs_declaration' => 'transport',
            'upd', 'invoice_factura', 'act' => 'closing',
            'invoice' => 'primary',
            default => 'other',
        };
    }

    private function requiresCounterpartySignature(string $type): bool
    {
        return in_array($type, ['request', 'upd', 'invoice_factura', 'act'], true);
    }

    /**
     * @param  object{counterparty_signed_at:mixed,counterparty_signed_file_path:?string,internal_signed_at:mixed,internal_signed_file_path:?string,signed_at:mixed,signed_by:mixed,status:?string}  $document
     */
    private function resolveSignatureStatus(object $document, bool $requiresCounterpartySignature): string
    {
        if (($document->counterparty_signed_at ?? null) !== null || ($document->counterparty_signed_file_path ?? null) !== null) {
            return 'signed_both_sides';
        }

        if (
            ($document->internal_signed_at ?? null) !== null
            || ($document->internal_signed_file_path ?? null) !== null
            || ($document->signed_at ?? null) !== null
            || ($document->signed_by ?? null) !== null
        ) {
            return $requiresCounterpartySignature ? 'signed_internal' : 'signed_both_sides';
        }

        if (in_array((string) ($document->status ?? ''), ['sent', 'pending'], true)) {
            return 'pending_signature';
        }

        return 'not_requested';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function buildScheduleSummary(array $schedule): ?string
    {
        $postpaymentDays = (int) Arr::get($schedule, 'postpayment_days', 0);
        $postpaymentMode = strtoupper((string) Arr::get($schedule, 'postpayment_mode', 'OTTN'));
        $hasPrepayment = (bool) Arr::get($schedule, 'has_prepayment', false);

        if (! $hasPrepayment) {
            return $postpaymentDays > 0 ? "{$postpaymentDays} дн {$postpaymentMode}" : null;
        }

        $prepaymentRatio = (int) Arr::get($schedule, 'prepayment_ratio', 0);
        $prepaymentDays = (int) Arr::get($schedule, 'prepayment_days', 0);
        $prepaymentMode = strtoupper((string) Arr::get($schedule, 'prepayment_mode', 'FTTN'));
        $postpaymentRatio = max(0, 100 - $prepaymentRatio);

        return "{$prepaymentRatio}/{$postpaymentRatio}, {$prepaymentDays} дн {$prepaymentMode} / {$postpaymentDays} дн {$postpaymentMode}";
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function decimalOrZero(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
