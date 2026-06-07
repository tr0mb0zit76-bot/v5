<?php

namespace App\Services\PrintForm;

use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Support\OrderPrintFormContext;
use App\Support\PrintFormBasicTermsTableCloner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrintFormBasicTermsService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('print_form_basic_terms');
    }

    /**
     * @return list<array{id: int|null, body: string, sort_order: int}>
     */
    public function listRows(string $party, ?int $contractorId = null): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return PrintFormBasicTerm::query()
            ->where('party', $party)
            ->when(
                $contractorId !== null,
                fn ($query) => $query->where('contractor_id', $contractorId),
                fn ($query) => $query->whereNull('contractor_id'),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'body', 'sort_order'])
            ->map(fn (PrintFormBasicTerm $term): array => [
                'id' => $term->id,
                'body' => (string) $term->body,
                'sort_order' => (int) $term->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $bodies
     */
    public function sync(string $party, ?int $contractorId, array $bodies): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $normalized = collect($bodies)
            ->map(fn (mixed $body): string => trim((string) $body))
            ->filter(fn (string $body): bool => $body !== '')
            ->values();

        DB::transaction(function () use ($party, $contractorId, $normalized): void {
            PrintFormBasicTerm::query()
                ->where('party', $party)
                ->when(
                    $contractorId !== null,
                    fn ($query) => $query->where('contractor_id', $contractorId),
                    fn ($query) => $query->whereNull('contractor_id'),
                )
                ->delete();

            foreach ($normalized as $index => $body) {
                PrintFormBasicTerm::query()->create([
                    'party' => $party,
                    'contractor_id' => $contractorId,
                    'sort_order' => $index + 1,
                    'body' => $body,
                ]);
            }
        });
    }

    /**
     * @return list<array<string, string>>
     */
    public function resolveTableRowsForPrint(
        Order $order,
        PrintFormTemplate $template,
        ?OrderPrintFormContext $context = null,
    ): array {
        $party = (string) ($template->party ?? '');

        if (! in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            return [];
        }

        return $this->resolveTableRowsForPrintParty($order, $party, $context);
    }

    /**
     * @return list<array<string, string>>
     */
    public function resolveTableRowsForPrintParty(
        Order $order,
        string $party,
        ?OrderPrintFormContext $context = null,
    ): array {
        if (! in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            return [];
        }

        $cloner = PrintFormBasicTermsTableCloner::forParty($party);

        if ($cloner === null) {
            return [];
        }

        $texts = $this->resolveTermBodies($order, $party, $context);

        return $this->formatRowsForCloner($cloner, $texts);
    }

    /**
     * @return list<string>
     */
    public function resolveTermBodies(Order $order, string $party, ?OrderPrintFormContext $context = null): array
    {
        $override = $this->orderOverrideBodies($order, $party);

        if ($override !== null) {
            return $override;
        }

        $contractorId = $this->resolveContractorIdForParty($order, $party, $context);

        if ($contractorId !== null) {
            $contractorBodies = $this->loadBodies($party, $contractorId);

            if ($contractorBodies !== []) {
                return $contractorBodies;
            }
        }

        return $this->loadBodies($party, null);
    }

    /**
     * @return list<string>|null
     */
    private function orderOverrideBodies(Order $order, string $party): ?array
    {
        if (! Schema::hasColumn('orders', 'customer_basic_terms') || ! Schema::hasColumn('orders', 'carrier_basic_terms')) {
            return null;
        }

        $column = $party === PrintFormBasicTerm::PARTY_CARRIER ? 'carrier_basic_terms' : 'customer_basic_terms';
        $raw = $order->getAttribute($column);

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $bodies = collect($raw)
            ->map(function (mixed $item): string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item)) {
                    return trim((string) ($item['body'] ?? $item['text'] ?? ''));
                }

                return '';
            })
            ->filter(fn (string $body): bool => $body !== '')
            ->values()
            ->all();

        return $bodies === [] ? null : $bodies;
    }

    private function resolveContractorIdForParty(Order $order, string $party, ?OrderPrintFormContext $context): ?int
    {
        if ($party === PrintFormBasicTerm::PARTY_CUSTOMER) {
            return $order->customer_id !== null ? (int) $order->customer_id : null;
        }

        if ($context?->carrierContractorId !== null) {
            return (int) $context->carrierContractorId;
        }

        return $order->carrier_id !== null ? (int) $order->carrier_id : null;
    }

    /**
     * @return list<string>
     */
    private function loadBodies(string $party, ?int $contractorId): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return PrintFormBasicTerm::query()
            ->where('party', $party)
            ->when(
                $contractorId !== null,
                fn ($query) => $query->where('contractor_id', $contractorId),
                fn ($query) => $query->whereNull('contractor_id'),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('body')
            ->map(fn (mixed $body): string => trim((string) $body))
            ->filter(fn (string $body): bool => $body !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<string, string>>
     */
    private function formatRowsForCloner(PrintFormBasicTermsTableCloner $cloner, array $texts): array
    {
        $rows = [];

        foreach (array_values($texts) as $index => $text) {
            $row = [];

            foreach ($cloner->rowMacroNames() as $macro) {
                if (str_ends_with($macro, '_index')) {
                    $row[$macro] = (string) ($index + 1);
                } else {
                    $row[$macro] = $text;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{
     *     items: list<string>,
     *     source: string,
     *     contractor_id: int|null,
     *     has_order_override: bool
     * }
     */
    public function wizardPayloadForOrder(Order $order, string $party): array
    {
        $override = $this->orderOverrideBodies($order, $party);
        $contractorId = $this->resolveContractorIdForParty($order, $party, null);
        $baseline = $this->resolveBaselineBodies($order, $party, null);
        $source = 'global';

        if ($override !== null) {
            $source = 'order';
        } elseif ($contractorId !== null && $this->loadBodies($party, $contractorId) !== []) {
            $source = 'contractor';
        }

        return [
            'items' => $override ?? $baseline,
            'baseline_items' => $baseline,
            'source' => $source,
            'contractor_id' => $contractorId,
            'has_order_override' => $override !== null,
            'differs_from_baseline' => $override !== null
                && ! $this->bodiesEqual($override, $baseline),
        ];
    }

    /**
     * @param  list<string>|null  $items
     * @return list<string>|null null when equal to baseline (clear override)
     */
    public function normalizeOrderOverride(?array $items, Order $order, string $party): ?array
    {
        $normalized = $this->normalizeBodiesList($items);

        if ($normalized === []) {
            return null;
        }

        $baseline = $this->resolveBaselineBodies($order, $party, null);

        return $this->bodiesEqual($normalized, $baseline) ? null : $normalized;
    }

    /**
     * @param  list<string>  $bodies
     */
    public function promoteOrderTermsToContractor(Order $order, string $party, array $bodies): void
    {
        $normalized = $this->normalizeBodiesList($bodies);

        if ($normalized === []) {
            return;
        }

        $contractorId = $this->resolveContractorIdForParty($order, $party, null);

        if ($contractorId === null) {
            throw new \InvalidArgumentException('Для сохранения базы нужен контрагент в заказе.');
        }

        $this->sync($party, $contractorId, $normalized);
    }

    /**
     * @return list<string>
     */
    private function resolveBaselineBodies(Order $order, string $party, ?OrderPrintFormContext $context): array
    {
        $contractorId = $this->resolveContractorIdForParty($order, $party, $context);

        if ($contractorId !== null) {
            $contractorBodies = $this->loadBodies($party, $contractorId);

            if ($contractorBodies !== []) {
                return $contractorBodies;
            }
        }

        return $this->loadBodies($party, null);
    }

    /**
     * @param  list<string>|null  $items
     * @return list<string>
     */
    private function normalizeBodiesList(?array $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item)) {
                    return trim((string) ($item['body'] ?? $item['text'] ?? ''));
                }

                return '';
            })
            ->filter(fn (string $body): bool => $body !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function bodiesEqual(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $body) {
            if ($body !== ($right[$index] ?? '')) {
                return false;
            }
        }

        return true;
    }
}
