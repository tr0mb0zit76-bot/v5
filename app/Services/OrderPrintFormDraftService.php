<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Support\CarrierPaymentTermResolver;
use App\Support\DocxVmlOverlayStylePatcher;
use App\Support\PaymentFormCodeLabel;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\PhpWordTemplateOverlayImageInjector;
use App\Support\PrintFormPlaceholderMacroVariants;
use App\Support\PrintFormPlaceholderPathResolver;
use App\Support\PrintFormTemplateDiskSource;
use App\Support\PrintFormTemplateOverlayAppearanceOrder;
use App\Support\RussianPositionInflector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class OrderPrintFormDraftService
{
    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
        private readonly PrintFormPlaceholderPathResolver $placeholderPathResolver,
    ) {}

    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function generate(PrintFormTemplate $template, Order $order, bool $includeTemplateOverlays = true): array
    {
        $templatePrep = PrintFormTemplateDiskSource::prepareLocalPathForPhpWord($template->file_disk, $template->file_path);
        try {
            $processor = new TemplateProcessor($templatePrep['path']);
        } finally {
            foreach ($templatePrep['tempFiles'] as $tmpPath) {
                if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        $settings = is_array($template->settings) ? $template->settings : [];
        $placeholders = collect($settings['variables'] ?? [])
            ->merge($this->placeholderExtractor->extractFromDisk($template->file_disk, $template->file_path))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();
        $mapping = collect($settings['variable_mapping'] ?? []);
        $snapshot = $this->buildSnapshot($this->loadOrderContext($order));
        $overlayPlaceholders = $this->overlayPlaceholderList($template);

        $processor->setMacroChars('${', '}');

        foreach ($placeholders as $placeholder) {
            if (in_array($placeholder, $overlayPlaceholders, true)) {
                continue;
            }

            $mappedPath = $this->resolveMappedPath($placeholder, $mapping, $template);
            $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

            foreach (PrintFormPlaceholderMacroVariants::innerPartsForSetValue($placeholder) as $inner) {
                $processor->setValue($inner, $replacement);
            }
        }

        if ($placeholders->isNotEmpty()) {
            $processor->setMacroChars('{{', '}}');

            foreach ($placeholders as $placeholder) {
                if (in_array($placeholder, $overlayPlaceholders, true)) {
                    continue;
                }

                $mappedPath = $this->resolveMappedPath($placeholder, $mapping, $template);
                $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

                foreach (PrintFormPlaceholderMacroVariants::innerPartsForSetValue($placeholder) as $inner) {
                    $processor->setValue($inner, $replacement);
                }
            }
        }

        $overlayStyles = [];
        $overlayTempFiles = [];
        if ($includeTemplateOverlays) {
            $overlayTempFiles = $this->injectTemplateOverlayImages($processor, $template);
            $overlayStyles = $this->buildOverlayFloatingStyles($template);
            if (! $template->shouldApplyCrmOverlayOffsets()) {
                $overlayStyles = array_map(
                    static fn (): array => ['margin_left_mm' => 0.0, 'margin_top_mm' => 0.0],
                    $overlayStyles,
                );
            }
        }

        $disk = 'local';
        $downloadName = Str::slug($template->code ?: 'template').'-order-'.$order->id.'-draft.docx';
        $storagePath = 'generated-documents/drafts/'.$template->id.'/'.Str::uuid().'-'.$downloadName;
        $absoluteTarget = Storage::disk($disk)->path($storagePath);
        $targetDirectory = dirname($absoluteTarget);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create directory: %s', $targetDirectory));
        }

        $processor->saveAs($absoluteTarget);

        foreach ($overlayTempFiles as $tmpPath) {
            if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        DocxVmlOverlayStylePatcher::patchDocx($absoluteTarget, $overlayStyles, true);

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'download_name' => $downloadName,
        ];
    }

    /**
     * @return list<array{margin_left_mm: float, margin_top_mm: float}>
     */
    private function buildOverlayFloatingStyles(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $keys = PrintFormTemplateOverlayAppearanceOrder::imageOverlayKeysInReadingOrder($template);

        return collect($keys)
            ->map(function (string $key) use ($overlays): array {
                $overlay = is_array($overlays[$key] ?? null) ? $overlays[$key] : [];

                return [
                    'margin_left_mm' => (float) ($overlay['offset_x_mm'] ?? 0),
                    'margin_top_mm' => (float) ($overlay['offset_y_mm'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Order $order): array
    {
        /** @var Collection<int, mixed> $routePoints */
        $routePoints = $order->relationLoaded('routePoints') ? $order->routePoints : collect();
        /** @var Collection<int, mixed> $cargoItems */
        $cargoItems = $order->relationLoaded('cargoItems') ? $order->cargoItems : collect();

        $loadingPoints = $routePoints->where('type', 'loading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $unloadingPoints = $routePoints->where('type', 'unloading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $fleetSelection = $this->resolvePrimaryFleetSelection($order);
        $driver = $this->driverPayload((int) ($order->driver_id ?? 0), $fleetSelection['fleet_driver_id']);
        $vehicle = $this->vehiclePayload($order, $driver, $fleetSelection['fleet_vehicle_id'], $cargoItems);
        $loadingMethod = $this->resolveLoadingMethod($loadingPoints->first(), $order);

        $cargoNames = $cargoItems
            ->map(fn ($cargo): ?string => $cargo->title ?: $cargo->description)
            ->filter()
            ->implode('; ');

        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->weight ?? 0) * $this->cargoPackageCountFactor($cargo));
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->volume ?? 0) * $this->cargoPackageCountFactor($cargo));
        $cargoTotalPackages = $cargoItems->sum(fn ($cargo): int => (int) ($cargo->package_count ?? $cargo->pallet_count ?? 0));

        $paymentTermsPayload = $this->decodeOrderPaymentTermsPayload($order);

        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => $this->formatDate($order->order_date),
                'loading_date' => $this->formatDate($order->loading_date),
                'unloading_date' => $this->formatDate($order->unloading_date),
                'status' => $order->status,
                'customer_rate' => $this->formatMoney($order->customer_rate),
                'carrier_rate' => $this->formatMoney($this->resolveCarrierRateValue($order)),
                'customer_rate_with_currency' => $this->formatMoneyWithCurrency(
                    $order->customer_rate,
                    $this->resolveCustomerCurrencyCode($order, $paymentTermsPayload),
                ),
                'carrier_rate_with_currency' => $this->formatMoneyWithCurrency(
                    $this->resolveCarrierRateValue($order),
                    $this->resolveCarrierCurrencyCode($order, $paymentTermsPayload),
                ),
                'customer_payment_form' => $this->resolveCustomerPaymentFormDisplay($order, $paymentTermsPayload),
                'customer_payment_term' => $this->resolveCustomerPaymentTermDisplay($order, $paymentTermsPayload),
                'carrier_payment_form' => $this->resolveCarrierPaymentFormDisplay($order, $paymentTermsPayload),
                'carrier_payment_term' => $this->resolveCarrierPaymentTermDisplay($order, $paymentTermsPayload),
                'invoice_number' => $order->invoice_number,
                'waybill_number' => $order->waybill_number,
                'special_notes' => $order->special_notes,
                'svh_name' => $order->svh_name,
                'svh_address' => $order->svh_address,
                'customs_post_code' => $order->customs_post_code,
                'customs_post_name' => null,
                'customs_declaration_place' => null,
                'customs_commodity_code' => null,
                'svh_summary' => $this->formatSvhSummaryBlock($order),
            ],
            'cargo_sender' => [
                'name' => $this->resolvePrimaryPartyValue($loadingPoints, 'sender_name'),
                'address' => $this->resolvePrimaryAddressValue($loadingPoints),
                // Backward compatibility: legacy mappings can still point to contact / phone.
                'contact' => $this->resolvePrimaryPartyContactPhone($loadingPoints, 'sender_contact', 'sender_phone'),
                'phone' => $this->resolvePrimaryPartyContactPhone($loadingPoints, 'sender_contact', 'sender_phone'),
                'contact_phone' => $this->resolvePrimaryPartyContactPhone($loadingPoints, 'sender_contact', 'sender_phone'),
                'all_names' => $this->resolvePartyList($loadingPoints, 'sender_name'),
                'all_addresses' => $this->resolvePartyAddressList($loadingPoints),
                'all_contact_phones' => $this->resolvePartyContactPhoneList($loadingPoints, 'sender_contact', 'sender_phone'),
            ],
            'cargo_recipient' => [
                'name' => $this->resolvePrimaryPartyValue($unloadingPoints, 'recipient_name'),
                'address' => $this->resolvePrimaryAddressValue($unloadingPoints),
                // Backward compatibility: legacy mappings can still point to contact / phone.
                'contact' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'phone' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'contact_phone' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'all_names' => $this->resolvePartyList($unloadingPoints, 'recipient_name'),
                'all_addresses' => $this->resolvePartyAddressList($unloadingPoints),
                'all_contact_phones' => $this->resolvePartyContactPhoneList($unloadingPoints, 'recipient_contact', 'recipient_phone'),
            ],
            'customer' => $this->contractorPayload($order->client),
            'carrier' => $this->contractorPayload($this->resolveCarrierContractorForPrint($order)),
            'own_company' => $this->contractorPayload($order->ownCompany, $order->own_company_bank_account_id),
            'manager' => [
                'name' => $order->manager?->name,
                'email' => $order->manager?->email,
                'phone' => $order->manager?->phone,
            ],
            'responsible' => [
                'name' => $order->manager?->name,
                'email' => $order->manager?->email,
                'phone' => $order->manager?->phone,
            ],
            'driver' => $driver,
            'vehicle' => $vehicle,
            'contacts' => [
                'customer_name' => $order->customer_contact_name,
                'customer_phone' => $order->customer_contact_phone,
                'customer_email' => $order->customer_contact_email,
                'carrier_name' => $order->carrier_contact_name,
                'carrier_phone' => $order->carrier_contact_phone,
                'carrier_email' => $order->carrier_contact_email,
            ],
            'route' => [
                'loading_addresses' => $this->resolvePartyAddressList($loadingPoints),
                'loading_cities' => $this->resolvePointCityList($loadingPoints),
                'loading_first_address' => $this->resolvePointAddress($loadingPoints->first()),
                'loading_first_city' => $this->resolvePointCity($loadingPoints->first()),
                'loading_time_range' => $this->resolvePointTimeRange($loadingPoints->first()),
                'loading_method' => $loadingMethod,
                'loading_types' => $this->resolveLoadingTypes($loadingPoints, $order),
                'unloading_addresses' => $this->resolvePartyAddressList($unloadingPoints),
                'unloading_cities' => $this->resolvePointCityList($unloadingPoints),
                'unloading_first_address' => $this->resolvePointAddress($unloadingPoints->first()),
                'unloading_first_city' => $this->resolvePointCity($unloadingPoints->first()),
                'unloading_last_city' => $this->resolvePointCity($unloadingPoints->last()),
                'unloading_last_address' => $this->resolvePointAddress($unloadingPoints->last()),
                'unloading_time_range' => $this->resolvePointTimeRange($unloadingPoints->first()),
            ],
            'cargo' => array_merge([
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => $this->cargoLineDetailTextForSummaryLine($cargo))
                    ->filter(fn (string $s): bool => $s !== '')
                    ->implode('  |  '),
                'lines_multiline' => $cargoItems
                    ->map(fn ($cargo): string => $this->cargoLineDetailText($cargo))
                    ->filter(fn (string $s): bool => $s !== '')
                    ->implode("\n\n"),
                'names' => $cargoNames,
                'total_weight' => $this->formatNumber($cargoTotalWeight),
                'total_weight_tons' => $this->formatNumber($cargoTotalWeight / 1000),
                'total_volume' => $this->formatVolumeNumber((float) $cargoTotalVolume),
                'total_packages' => (string) $cargoTotalPackages,
                'cargo_types' => $this->resolveCargoScalarList($cargoItems, ['cargo_type_label', 'cargo_type']),
                'pack_types' => $this->resolveCargoScalarList($cargoItems, ['pack_type_label', 'packing_type']),
                'loading_types' => $this->resolveCargoDictionaryItemLabels($cargoItems, 'loading_type_items', 'loading_type_label'),
                'truck_body_types' => $this->resolveCargoDictionaryItemLabels($cargoItems, 'truck_body_type_items', 'truck_body_type_label'),
                'trailer_types' => $this->resolveCargoDictionaryItemLabels($cargoItems, 'trailer_type_items', 'trailer_type_label'),
                'hazard_classes' => $this->resolveCargoHazardClassesSummary($cargoItems),
                'hs_codes' => $this->resolveCargoHsCodesSummary($cargoItems),
                'first_hs_code' => $this->resolveCargoFirstHsCode($cargoItems),
            ], $this->cargoPerLinePlaceholderMap($cargoItems)),
            'financial' => $this->financialNormsPenaltiesSnapshot($order),
        ];
    }

    /**
     * @return array{fleet_vehicle_id:int|null,fleet_driver_id:int|null}
     */
    private function resolvePrimaryFleetSelection(Order $order): array
    {
        if ($order->relationLoaded('legs')) {
            foreach ($order->legs->sortBy('sequence') as $leg) {
                $performer = is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : [];
                $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                    ? (int) $performer['fleet_vehicle_id']
                    : null;
                $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                    ? (int) $performer['fleet_driver_id']
                    : null;

                if ($vehicleId !== null || $driverId !== null) {
                    return [
                        'fleet_vehicle_id' => $vehicleId,
                        'fleet_driver_id' => $driverId,
                    ];
                }
            }
        }

        $performers = is_array($order->performers) ? $order->performers : [];
        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                ? (int) $performer['fleet_vehicle_id']
                : null;
            $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                ? (int) $performer['fleet_driver_id']
                : null;

            if ($vehicleId !== null || $driverId !== null) {
                return [
                    'fleet_vehicle_id' => $vehicleId,
                    'fleet_driver_id' => $driverId,
                ];
            }
        }

        return [
            'fleet_vehicle_id' => null,
            'fleet_driver_id' => null,
        ];
    }

    private function resolveCarrierRateValue(Order $order): ?float
    {
        if ($order->carrier_rate !== null && $order->carrier_rate !== '') {
            return (float) $order->carrier_rate;
        }

        if ($order->relationLoaded('legs') && Schema::hasTable('leg_costs')) {
            $sumFromLegs = $order->legs
                ->map(fn ($leg): float => (float) ($leg->cost?->amount ?? 0))
                ->sum();
            if ($sumFromLegs > 0) {
                return $sumFromLegs;
            }
        }

        if ($order->relationLoaded('financialTerms')) {
            $costs = $order->financialTerms->first()?->contractors_costs;
            if (is_array($costs)) {
                $sumFromCosts = collect($costs)->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
                if ($sumFromCosts > 0) {
                    return $sumFromCosts;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeOrderPaymentTermsPayload(Order $order): ?array
    {
        $raw = $order->getAttribute('payment_terms');
        if (($raw === null || $raw === '') && Schema::hasTable('financial_terms')) {
            $ft = $order->financialTerms->first();
            if ($ft !== null && Schema::hasColumn($ft->getTable(), 'payment_terms_snapshot')) {
                $snap = $ft->getAttribute('payment_terms_snapshot');
                if (filled($snap)) {
                    $raw = $snap;
                }
            }
        }

        if ($raw === null || $raw === '') {
            $fromCostsOnly = $this->mergeCarriersFromFinancialTermsIfMissing($order, []);

            return isset($fromCostsOnly['carriers']) && $fromCostsOnly['carriers'] !== []
                ? $fromCostsOnly
                : null;
        }

        if (is_array($raw)) {
            return $this->mergeCarriersFromFinancialTermsIfMissing($order, $raw);
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $this->mergeCarriersFromFinancialTermsIfMissing($order, $decoded) : null;
    }

    /**
     * В печатной форме нужен тот же блок «перевозчики», что в мастере: он может жить только в {@see FinancialTerm::contractors_costs}.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function mergeCarriersFromFinancialTermsIfMissing(Order $order, array $decoded): array
    {
        $carriers = $decoded['carriers'] ?? null;
        if (is_array($carriers) && $carriers !== []) {
            return $decoded;
        }

        if (! $order->relationLoaded('financialTerms')) {
            return $decoded;
        }

        $ft = $order->financialTerms->first();
        $costs = is_array($ft?->contractors_costs) ? $ft->contractors_costs : [];
        if ($costs === []) {
            return $decoded;
        }

        $decoded['carriers'] = collect($costs)
            ->map(function (array $c): array {
                $schedule = $c['payment_schedule'] ?? [];
                if (! is_array($schedule)) {
                    $schedule = [];
                }

                return [
                    'stage' => $c['stage'] ?? null,
                    'contractor_id' => isset($c['contractor_id']) && $c['contractor_id'] !== null ? (int) $c['contractor_id'] : null,
                    'payment_form' => $c['payment_form'] ?? null,
                    'currency' => $c['currency'] ?? null,
                    'payment_schedule' => $schedule,
                ];
            })
            ->values()
            ->all();

        return $decoded;
    }

    /**
     * Реквизиты перевозчика: при пустом {@see Order::$carrier_id} берём контрагента из первой строки затрат по плечу.
     */
    private function resolveCarrierContractorForPrint(Order $order): mixed
    {
        if ($order->carrier) {
            return $order->carrier;
        }

        $contractorId = $this->firstCarrierContractorIdFromFinancialTerms($order);
        if ($contractorId !== null) {
            return Contractor::query()->find($contractorId) ?? $order->carrier;
        }

        return $order->carrier;
    }

    private function firstCarrierContractorIdFromFinancialTerms(Order $order): ?int
    {
        if (! $order->relationLoaded('financialTerms')) {
            return null;
        }

        $ft = $order->financialTerms->first();
        $costs = is_array($ft?->contractors_costs) ? $ft->contractors_costs : [];
        foreach ($costs as $c) {
            if (! is_array($c)) {
                continue;
            }
            $id = $c['contractor_id'] ?? null;
            if ($id !== null && (int) $id > 0) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCustomerPaymentFormDisplay(Order $order, ?array $paymentTermsPayload): ?string
    {
        $fromPayload = data_get($paymentTermsPayload, 'client.payment_form');

        $code = is_string($fromPayload) && $fromPayload !== ''
            ? $fromPayload
            : $order->customer_payment_form;

        return PaymentFormCodeLabel::toDisplay(is_string($code) ? $code : null);
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCustomerPaymentTermDisplay(Order $order, ?array $paymentTermsPayload): ?string
    {
        $schedule = data_get($paymentTermsPayload, 'client.payment_schedule');
        if (is_array($schedule) && $schedule !== []) {
            return PaymentScheduleSummaryFormatter::format(
                $schedule,
                (float) ($order->customer_rate ?? 0),
                'RUB',
                $order,
                [],
            );
        }

        return PaymentScheduleSummaryFormatter::humanizeStoredSummary($order->customer_payment_term);
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCarrierPaymentFormDisplay(Order $order, ?array $paymentTermsPayload): ?string
    {
        $carriers = data_get($paymentTermsPayload, 'carriers');
        if (is_array($carriers) && $carriers !== []) {
            $forms = collect($carriers)
                ->pluck('payment_form')
                ->filter(fn (mixed $v): bool => is_string($v) && $v !== '')
                ->unique()
                ->values();

            if ($forms->count() === 1) {
                return PaymentFormCodeLabel::toDisplay((string) $forms->first());
            }

            if ($forms->count() > 1) {
                return PaymentFormCodeLabel::toDisplay('mixed');
            }
        }

        return PaymentFormCodeLabel::toDisplay($order->carrier_payment_form);
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCarrierPaymentTermDisplay(Order $order, ?array $paymentTermsPayload): ?string
    {
        $carriers = data_get($paymentTermsPayload, 'carriers');
        if (is_array($carriers) && $carriers !== []) {
            $fromCosts = CarrierPaymentTermResolver::fromContractorsCostsArray($carriers);
            if ($fromCosts !== null && $fromCosts !== '') {
                return $fromCosts;
            }
        }

        return PaymentScheduleSummaryFormatter::humanizeStoredSummary($order->carrier_payment_term);
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCustomerCurrencyCode(Order $order, ?array $paymentTermsPayload): string
    {
        $fromPayload = data_get($paymentTermsPayload, 'client.currency')
            ?? data_get($paymentTermsPayload, 'client.client_currency')
            ?? data_get($paymentTermsPayload, 'client_currency');

        $currency = is_string($fromPayload) && trim($fromPayload) !== ''
            ? strtoupper(trim($fromPayload))
            : 'RUB';

        return $currency;
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     */
    private function resolveCarrierCurrencyCode(Order $order, ?array $paymentTermsPayload): string
    {
        $carriers = data_get($paymentTermsPayload, 'carriers');
        if (is_array($carriers) && $carriers !== []) {
            $currencies = collect($carriers)
                ->pluck('currency')
                ->filter(fn (mixed $v): bool => is_string($v) && trim($v) !== '')
                ->map(fn (string $v): string => strtoupper(trim($v)))
                ->unique()
                ->values();

            if ($currencies->count() === 1) {
                return (string) $currencies->first();
            }
        }

        return 'RUB';
    }

    public function loadOrderContext(Order $order): Order
    {
        $relations = ['client', 'carrier', 'ownCompany', 'manager'];

        if (Schema::hasTable('order_legs') && Schema::hasTable('route_points')) {
            $relations[] = 'routePoints';
        }

        if (Schema::hasTable('cargos')) {
            $relations[] = 'cargoItems';
        }

        if (Schema::hasTable('order_legs')) {
            $relations[] = 'legs';

            if (Schema::hasTable('leg_costs')) {
                $relations[] = 'legs.cost';
            }
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        return $order->loadMissing($relations);
    }

    /**
     * @return array<string, mixed>
     */
    private function contractorPayload(mixed $contractor, ?string $preferredOwnCompanyBankAccountId = null): array
    {
        $acct = $contractor instanceof Contractor
            ? $contractor->bankDetailsForAccountId($preferredOwnCompanyBankAccountId)
            : [
                'bank_name' => null,
                'bik' => null,
                'account_number' => null,
                'correspondent_account' => null,
            ];

        $nonResident = $contractor instanceof Contractor ? $contractor->nonResidentPrintPayload() : [
            'is_non_resident' => 'Нет',
            'non_resident_corr_bank_name' => null,
            'non_resident_corr_bank_swift' => null,
            'non_resident_corr_settlement_account' => null,
            'non_resident_corr_bank_account' => null,
            'cnaps_code' => null,
        ];

        return [
            'name' => $contractor?->name,
            'full_name' => $contractor?->full_name,
            'inn' => $contractor?->inn,
            'kpp' => $contractor?->kpp,
            'ogrn' => $contractor?->ogrn,
            'legal_address' => $contractor?->legal_address,
            'actual_address' => $contractor?->actual_address,
            'postal_address' => $contractor?->postal_address,
            'phone' => $contractor?->phone,
            'email' => $contractor?->email,
            'contact_person' => $contractor?->contact_person,
            'bank_name' => $this->firstFilledValue([$contractor?->bank_name, $acct['bank_name']]),
            'bik' => $this->firstFilledValue([$contractor?->bik, $acct['bik']]),
            'account_number' => $this->firstFilledValue([$contractor?->account_number, $acct['account_number']]),
            'correspondent_account' => $this->firstFilledValue([$contractor?->correspondent_account, $acct['correspondent_account']]),
            'signer_name_nominative' => $contractor?->signer_name_nominative,
            'signer_name_prepositional' => $contractor?->signer_name_prepositional,
            'signer_position' => $contractor?->signer_position ?? $contractor?->contact_person_position,
            'signer_position_genitive_auto' => RussianPositionInflector::toGenitive($contractor?->signer_position ?? $contractor?->contact_person_position),
            'signer_authority_basis' => $contractor?->signer_authority_basis,
            ...$nonResident,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function driverPayload(int $driverId, ?int $fleetDriverId = null): array
    {
        if ($fleetDriverId !== null && Schema::hasTable('fleet_drivers')) {
            /** @var FleetDriver|null $fleetDriver */
            $fleetDriver = FleetDriver::query()->find($fleetDriverId);
            if ($fleetDriver !== null) {
                $passportParts = array_filter([
                    $fleetDriver->passport_series,
                    $fleetDriver->passport_number,
                    $fleetDriver->passport_issued_by,
                    $fleetDriver->passport_issued_at?->format('d.m.Y'),
                ]);

                return [
                    'full_name' => $fleetDriver->full_name,
                    'phone' => $fleetDriver->phone,
                    'passport_data' => $passportParts !== [] ? implode(', ', $passportParts) : null,
                ];
            }
        }

        if ($driverId <= 0 || ! Schema::hasTable('drivers')) {
            return [
                'full_name' => null,
                'phone' => null,
                'passport_data' => null,
            ];
        }

        $driver = DB::table('drivers')
            ->select('first_name', 'last_name', 'patronymic', 'phone', 'metadata')
            ->where('id', $driverId)
            ->first();

        if ($driver === null) {
            return [
                'full_name' => null,
                'phone' => null,
                'passport_data' => null,
            ];
        }

        $metadata = is_string($driver->metadata) ? json_decode($driver->metadata, true) : $driver->metadata;
        $passportData = is_array($metadata) ? data_get($metadata, 'passport_data', data_get($metadata, 'passport')) : null;

        return [
            'full_name' => trim(implode(' ', array_filter([
                $driver->last_name,
                $driver->first_name,
                $driver->patronymic,
            ]))) ?: null,
            'phone' => $driver->phone,
            'passport_data' => is_scalar($passportData) ? (string) $passportData : null,
        ];
    }

    /**
     * Марка/номер — из исполнителя и метаданных; «кузов» — только из позиций груза (как на вкладке «Груз»).
     * Поле типа ТС / «тягач» в снимок не включаем — оно путалось с кузовом в печатных формах.
     *
     * @param  array<string, string|null>  $driver
     * @param  Collection<int, mixed>  $cargoItems
     * @return array{brand: ?string, number: ?string, cargo_body_type: ?string, trailer_type: ?string}
     */
    private function vehiclePayload(Order $order, array $driver, ?int $fleetVehicleId, Collection $cargoItems): array
    {
        $cargoTruckBody = $this->resolveCargoDictionaryItemLabels($cargoItems, 'truck_body_type_items', 'truck_body_type_label');

        if ($fleetVehicleId !== null && Schema::hasTable('fleet_vehicles')) {
            /** @var FleetVehicle|null $fleetVehicle */
            $fleetVehicle = FleetVehicle::query()->find($fleetVehicleId);
            if ($fleetVehicle !== null) {
                return [
                    'brand' => $this->firstFilledValue([$fleetVehicle->tractor_brand, $fleetVehicle->trailer_brand]),
                    'number' => $this->firstFilledValue([
                        $fleetVehicle->tractor_plate,
                        $fleetVehicle->trailer_plate,
                    ]),
                    'cargo_body_type' => $cargoTruckBody,
                    'trailer_type' => $cargoTruckBody,
                ];
            }
        }

        $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
        $orderWizardState = is_array($order->wizard_state) ? $order->wizard_state : [];

        return [
            'brand' => $this->firstFilledValue([
                data_get($driver, 'vehicle_brand'),
                data_get($driver, 'brand'),
                data_get($orderWizardState, 'vehicle.brand'),
                data_get($orderWizardState, 'transport.vehicle_brand'),
                data_get($orderMetadata, 'vehicle.brand'),
                data_get($orderMetadata, 'vehicle_brand'),
            ]),
            'number' => $this->firstFilledValue([
                data_get($driver, 'vehicle_number'),
                data_get($driver, 'car_number'),
                data_get($orderWizardState, 'vehicle.number'),
                data_get($orderWizardState, 'transport.vehicle_number'),
                data_get($orderMetadata, 'vehicle.number'),
                data_get($orderMetadata, 'vehicle_number'),
                data_get($orderMetadata, 'gosnomer'),
            ]),
            'cargo_body_type' => $cargoTruckBody,
            'trailer_type' => $cargoTruckBody,
        ];
    }

    private function resolveLoadingMethod(mixed $firstLoadingPoint, Order $order): ?string
    {
        $normalizedData = is_array($firstLoadingPoint?->normalized_data) ? $firstLoadingPoint->normalized_data : [];
        $pointMetadata = is_array($firstLoadingPoint?->metadata) ? $firstLoadingPoint->metadata : [];
        $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
        $orderWizardState = is_array($order->wizard_state) ? $order->wizard_state : [];

        return $this->firstFilledValue([
            data_get($normalizedData, 'loading_method'),
            data_get($pointMetadata, 'loading_method'),
            data_get($pointMetadata, 'loading_type'),
            data_get($orderWizardState, 'loading_method'),
            data_get($orderWizardState, 'transport.loading_method'),
            data_get($orderMetadata, 'loading_method'),
            data_get($orderMetadata, 'loading_type'),
        ]);
    }

    private function firstFilledValue(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolvePrimaryPartyValue(Collection $points, string $key): ?string
    {
        $values = $points
            ->map(function (mixed $point) use ($key): ?string {
                $value = data_get($point, $key);
                if (! is_scalar($value)) {
                    return null;
                }

                $trimmed = trim((string) $value);

                return $trimmed === '' ? null : $trimmed;
            })
            ->filter()
            ->unique()
            ->values();

        if ($values->count() === 1) {
            return $values->first();
        }

        $first = data_get($points->first(), $key);
        if (! is_scalar($first)) {
            return null;
        }

        $trimmed = trim((string) $first);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string> Temporary filesystem paths created for PhpWord (delete after saveAs).
     */
    private function injectTemplateOverlayImages(TemplateProcessor $processor, PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        return array_values(array_filter(array_merge(
            $this->injectSingleOverlayImage($processor, is_array($overlays['internal_signature'] ?? null) ? $overlays['internal_signature'] : [], 'internal_signature_image'),
            $this->injectSingleOverlayImage($processor, is_array($overlays['internal_stamp'] ?? null) ? $overlays['internal_stamp'] : [], 'internal_stamp_image'),
        )));
    }

    /**
     * @return list<string>
     */
    private function overlayPlaceholderList(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        return collect(['internal_signature', 'internal_stamp'])
            ->map(function (string $key) use ($overlays): string {
                $placeholder = trim((string) data_get($overlays, $key.'.placeholder', $key === 'internal_signature' ? 'internal_signature_image' : 'internal_stamp_image'));

                return $placeholder !== '' ? $placeholder : ($key === 'internal_signature' ? 'internal_signature_image' : 'internal_stamp_image');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $overlay
     * @return list<string>
     */
    private function injectSingleOverlayImage(TemplateProcessor $processor, array $overlay, string $defaultPlaceholder): array
    {
        $path = $overlay['path'] ?? null;
        if (! is_string($path) || $path === '') {
            return [];
        }

        $disk = (string) ($overlay['disk'] ?? 'local');
        if (! Storage::disk($disk)->exists($path)) {
            return [];
        }

        $resolved = $this->resolveOverlayImageAbsolutePathForPhpWord($disk, $path);
        if ($resolved === null) {
            return [];
        }

        $placeholder = trim((string) ($overlay['placeholder'] ?? $defaultPlaceholder));
        if ($placeholder === '') {
            $placeholder = $defaultPlaceholder;
        }

        $widthMm = (float) ($overlay['width_mm'] ?? 30);
        $heightMm = (float) ($overlay['height_mm'] ?? 30);
        $widthPx = max(20, (int) round($widthMm * 3.78));
        $heightPx = max(20, (int) round($heightMm * 3.78));

        $absolutePath = $resolved['absolute'];

        PhpWordTemplateOverlayImageInjector::injectImageForAllMacroStyles($processor, $placeholder, [
            'path' => $absolutePath,
            'width' => $widthPx,
            'height' => $heightPx,
            'ratio' => true,
        ]);

        return $resolved['cleanup'];
    }

    /**
     * PhpWord needs a readable local path. Non-local disks (or adapters without a real path) copy into a temp file.
     *
     * @return array{absolute: string, cleanup: list<string>}|null
     */
    private function resolveOverlayImageAbsolutePathForPhpWord(string $disk, string $path): ?array
    {
        $filesystem = Storage::disk($disk);

        try {
            $candidate = $filesystem->path($path);
            if (is_file($candidate)) {
                return ['absolute' => $candidate, 'cleanup' => []];
            }
        } catch (\Throwable) {
            // Flysystem adapters without a local path() throw or return unusable paths.
        }

        $contents = $filesystem->get($path);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'png';
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'crm-tpl-overlay-');
        if ($tmpBase === false) {
            return null;
        }

        @unlink($tmpBase);
        $absolute = $tmpBase.'.'.$ext;
        file_put_contents($absolute, $contents);

        return ['absolute' => $absolute, 'cleanup' => [$absolute]];
    }

    private function resolvePrimaryAddressValue(Collection $points): ?string
    {
        $values = $points
            ->map(fn (mixed $point): ?string => $this->resolvePointAddress($point))
            ->filter()
            ->unique()
            ->values();

        if ($values->count() === 1) {
            return $values->first();
        }

        return $this->resolvePointAddress($points->first());
    }

    private function resolvePrimaryPartyContactPhone(Collection $points, string $contactKey, string $phoneKey): ?string
    {
        $pairs = $points
            ->map(fn (mixed $point): ?string => $this->buildContactPhoneValue(
                data_get($point, $contactKey),
                data_get($point, $phoneKey),
            ))
            ->filter()
            ->unique()
            ->values();

        if ($pairs->count() === 1) {
            return $pairs->first();
        }

        return $this->buildContactPhoneValue(
            data_get($points->first(), $contactKey),
            data_get($points->first(), $phoneKey),
        );
    }

    private function resolvePartyList(Collection $points, string $key): ?string
    {
        $values = $points
            ->map(function (mixed $point) use ($key): ?string {
                $value = data_get($point, $key);
                if (! is_scalar($value)) {
                    return null;
                }

                $trimmed = trim((string) $value);

                return $trimmed === '' ? null : $trimmed;
            })
            ->filter()
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->implode('; ');
    }

    private function resolvePartyAddressList(Collection $points): ?string
    {
        $values = $points
            ->map(fn (mixed $point): ?string => $this->resolvePointAddress($point))
            ->filter()
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->implode('; ');
    }

    private function resolvePointAddress(mixed $point): ?string
    {
        if ($point === null) {
            return null;
        }

        $address = $this->firstFilledValue([
            data_get($point, 'address'),
            data_get($point, 'metadata.address'),
            data_get($point, 'metadata.full_address'),
            data_get($point, 'normalized_data.result'),
            data_get($point, 'instructions'),
        ]);

        return $address;
    }

    private function resolvePointCityList(Collection $points): ?string
    {
        $cities = $points
            ->map(fn (mixed $point): ?string => $this->resolvePointCity($point))
            ->filter()
            ->unique()
            ->values();

        return $cities->isEmpty() ? null : $cities->implode('; ');
    }

    private function resolvePointCity(mixed $point): ?string
    {
        if ($point === null) {
            return null;
        }

        $city = $this->firstFilledValue([
            data_get($point, 'normalized_data.city'),
            data_get($point, 'normalized_data.settlement'),
            data_get($point, 'metadata.city'),
            data_get($point, 'metadata.settlement'),
        ]);

        if ($city !== null) {
            return $city;
        }

        $address = $this->resolvePointAddress($point);
        if ($address === null) {
            return null;
        }

        $firstPart = trim((string) preg_replace('/^(?:г\.?|город|с\.?|село|д\.?|деревня|пгт)\s+/iu', '', strtok($address, ',') ?: ''));

        return $firstPart !== '' ? $firstPart : null;
    }

    private function resolvePointTimeValue(mixed $point, string $key): ?string
    {
        if ($point === null) {
            return null;
        }

        $value = data_get($point, $key);
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 5, 'UTF-8');
    }

    private function resolvePointTimeRange(mixed $point): ?string
    {
        $from = $this->resolvePointTimeValue($point, 'planned_time_from');
        $to = $this->resolvePointTimeValue($point, 'planned_time_to');

        return match (true) {
            $from !== null && $to !== null => $from.'-'.$to,
            $from !== null => $from,
            $to !== null => 'до '.$to,
            default => null,
        };
    }

    private function resolveLoadingTypes(Collection $loadingPoints, Order $order): ?string
    {
        $types = $loadingPoints
            ->flatMap(function (mixed $point): array {
                $candidates = data_get($point, 'metadata.loading_types', data_get($point, 'normalized_data.loading_types', []));
                if (! is_array($candidates)) {
                    return [];
                }

                return $candidates;
            })
            ->map(fn (mixed $type): ?string => $this->normalizeLoadingType($type))
            ->filter()
            ->unique()
            ->values();

        if ($types->isEmpty()) {
            $fallback = data_get($order->wizard_state, 'loading_types', data_get($order->metadata, 'loading_types', []));
            if (is_array($fallback)) {
                $types = collect($fallback)
                    ->map(fn (mixed $type): ?string => $this->normalizeLoadingType($type))
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        if ($types->isEmpty()) {
            return null;
        }

        return $types->implode(', ');
    }

    private function normalizeLoadingType(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            'top', 'верх' => 'верх',
            'side', 'бок' => 'бок',
            'rear', 'зад' => 'зад',
            default => null,
        };
    }

    private function resolvePartyContactPhoneList(Collection $points, string $contactKey, string $phoneKey): ?string
    {
        $values = $points
            ->map(fn (mixed $point): ?string => $this->buildContactPhoneValue(
                data_get($point, $contactKey),
                data_get($point, $phoneKey),
            ))
            ->filter()
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->implode('; ');
    }

    private function buildContactPhoneValue(mixed $contact, mixed $phone): ?string
    {
        $contactValue = is_scalar($contact) ? trim((string) $contact) : '';
        $phoneValue = is_scalar($phone) ? trim((string) $phone) : '';

        if ($contactValue !== '' && $phoneValue !== '') {
            return $contactValue.', '.$phoneValue;
        }

        if ($contactValue !== '') {
            return $contactValue;
        }

        if ($phoneValue !== '') {
            return $phoneValue;
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     * @param  list<string>  $fields
     */
    private function resolveCargoScalarList(Collection $cargoItems, array $fields): ?string
    {
        $values = $cargoItems
            ->map(fn (mixed $cargo): ?string => is_object($cargo)
                ? $this->firstFilledValue(array_map(fn (string $field): mixed => data_get($cargo, $field), $fields))
                : null)
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     */
    private function resolveCargoDictionaryItemLabels(Collection $cargoItems, string $itemsField, string $labelField): ?string
    {
        $values = $cargoItems
            ->flatMap(fn (mixed $cargo): array => $this->dictionaryLabelsForCargo($cargo, $itemsField, $labelField))
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }

    /**
     * @return list<string>
     */
    private function dictionaryLabelsForCargo(mixed $cargo, string $itemsField, string $labelField): array
    {
        if (! is_object($cargo)) {
            return [];
        }

        $labels = [];
        $items = data_get($cargo, $itemsField);
        if (is_array($items)) {
            foreach ($items as $item) {
                $label = $this->firstFilledValue([
                    data_get($item, 'label'),
                    data_get($item, 'code'),
                    is_scalar($item) ? $item : null,
                ]);

                if ($label !== null) {
                    $labels[] = $label;
                }
            }
        }

        $fallbackLabel = $this->firstFilledValue([data_get($cargo, $labelField)]);
        if ($fallbackLabel !== null) {
            $labels[] = $fallbackLabel;
        }

        return $labels;
    }

    /**
     * Число мест для расчёта суммарного веса/объёма по строке груза (как в мастере заказа, package_count).
     */
    private function cargoPackageCountFactor(mixed $cargo): int
    {
        if (! is_object($cargo)) {
            return 1;
        }

        $n = (float) ($cargo->package_count ?? 0);

        return ($n > 0 && is_finite($n)) ? max(1, (int) $n) : 1;
    }

    /**
     * Только наименование/описание позиции (без веса и прочего блока).
     */
    private function cargoLineNameOnly(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        return trim((string) ($cargo->title ?? '') !== '' ? (string) $cargo->title : (string) ($cargo->description ?? ''));
    }

    /**
     * Текст одной позиции груза: как блок «Сводка позиции» в мастере (вес/объём с учётом мест, габариты, число мест).
     */
    private function cargoLineDetailText(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $name = $this->cargoLineNameOnly($cargo);
        $factor = $this->cargoPackageCountFactor($cargo);
        $perWeightKg = (float) ($cargo->weight ?? 0);
        $totalWeightKg = $perWeightKg * $factor;

        $lines = [];
        $weightLine = 'Вес: '.$this->formatNumber($totalWeightKg).' кг';
        if ($factor > 1) {
            $weightLine .= ' ('.$this->formatNumber($perWeightKg).' кг × '.$factor.')';
        }
        $lines[] = $weightLine;

        $perVol = (float) ($cargo->volume ?? 0);
        $totalVol = $perVol * $factor;
        if ($totalVol > 0.0) {
            $volLine = 'Объём: '.$this->formatVolumeNumber($totalVol).' м³';
            if ($factor > 1) {
                $volLine .= ' ('.$this->formatVolumeNumber($perVol).' м³ × '.$factor.')';
            }
            $lines[] = $volLine;
        } else {
            $lines[] = 'Объём: —';
        }

        $dimLine = $this->cargoDimensionsSummaryLine($cargo);
        if ($dimLine !== null) {
            $lines[] = $dimLine;
        }

        $lines[] = 'Мест: '.(int) ($cargo->package_count ?? 0);

        $body = implode("\n", $lines);

        return $name !== '' ? $name."\n".$body : $body;
    }

    /**
     * Одна строка для плейсхолдера «сводка по грузу»: без переводов строк, позиции через разделитель.
     */
    private function cargoLineDetailTextForSummaryLine(mixed $cargo): string
    {
        $block = $this->cargoLineDetailText($cargo);

        return trim(preg_replace("/\s+/u", ' ', str_replace(["\r\n", "\n", "\r"], ' ', $block)) ?? '');
    }

    private function cargoDimensionsSummaryLine(mixed $cargo): ?string
    {
        if (! is_object($cargo)) {
            return null;
        }

        $l = $cargo->length ?? null;
        $w = $cargo->width ?? null;
        $h = $cargo->height ?? null;

        if ($l === null && $w === null && $h === null) {
            return null;
        }

        $lf = $l !== null ? $this->formatNumber((float) $l) : '—';
        $wf = $w !== null ? $this->formatNumber((float) $w) : '—';
        $hf = $h !== null ? $this->formatNumber((float) $h) : '—';

        return 'Габариты (Д×Ш×В): '.$lf.'×'.$wf.'×'.$hf.' м';
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     * @return array<string, string>
     */
    private function cargoPerLinePlaceholderMap(Collection $cargoItems): array
    {
        $out = [];
        $values = $cargoItems->values();
        for ($i = 1; $i <= 10; $i++) {
            $cargo = $values->get($i - 1);
            $out['line_'.$i.'_text'] = $cargo !== null ? $this->cargoLineDetailText($cargo) : '';
            $out['line_'.$i.'_name'] = $cargo !== null ? $this->cargoLineNameOnly($cargo) : '';
            $out['line_'.$i.'_summary'] = $cargo !== null ? $this->cargoLineDetailTextForSummaryLine($cargo) : '';
        }

        return $out;
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     */
    private function resolveCargoHazardClassesSummary(Collection $cargoItems): string
    {
        $parts = $cargoItems
            ->filter(fn (mixed $cargo): bool => is_object($cargo) && (bool) ($cargo->is_hazardous ?? false))
            ->map(fn (mixed $cargo): string => trim((string) ($cargo->hazard_class ?? '')))
            ->filter(fn (string $s): bool => $s !== '')
            ->unique()
            ->values()
            ->all();

        return $parts !== [] ? implode(', ', $parts) : '';
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     */
    private function resolveCargoHsCodesSummary(Collection $cargoItems): string
    {
        $parts = $cargoItems
            ->map(fn (mixed $cargo): string => is_object($cargo) ? trim((string) ($cargo->hs_code ?? '')) : '')
            ->filter(fn (string $s): bool => $s !== '')
            ->unique()
            ->values()
            ->all();

        return $parts !== [] ? implode(', ', $parts) : '';
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     */
    private function resolveCargoFirstHsCode(Collection $cargoItems): ?string
    {
        foreach ($cargoItems as $cargo) {
            if (! is_object($cargo)) {
                continue;
            }
            $code = trim((string) ($cargo->hs_code ?? ''));
            if ($code !== '') {
                return $code;
            }
        }

        return null;
    }

    /**
     * Сводка по СВХ / таможне для старых макетов с одним блоком текста.
     */
    private function formatSvhSummaryBlock(Order $order): string
    {
        $lines = [];

        $postCode = trim((string) ($order->customs_post_code ?? ''));
        $svhName = trim((string) ($order->svh_name ?? ''));
        if ($postCode !== '' || $svhName !== '') {
            $postLine = $postCode;
            if ($svhName !== '') {
                $postLine = $postLine !== '' ? $postLine.' — '.$svhName : $svhName;
            }
            $lines[] = $postLine;
        }

        $address = trim((string) ($order->svh_address ?? ''));
        if ($address !== '') {
            $lines[] = $address;
        }

        return implode("\n", $lines);
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $piece = $this->stringifyValue($item);
                if ($piece !== '') {
                    $parts[] = $piece;
                }
            }

            return implode(', ', $parts);
        }

        return '';
    }

    /**
     * Плейсхолдеры вида stoimost, stoimost'_zak, stoimost'_perevoz (типографский апостроф допускается).
     */
    private function normalizedPlaceholderIsCarrierContractValue(string $placeholder): bool
    {
        $squashed = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", "'", '`', '´', ' ', '_'], '', mb_strtolower(trim($placeholder), 'UTF-8'));

        return in_array($squashed, ['stoimost', 'stoimostzak', 'stoimostperevoz'], true);
    }

    private function resolveMappedPath(string $placeholder, Collection $mapping, PrintFormTemplate $template): string
    {
        $resolved = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'order', $template->party);

        // Шаблон перевозчика: «стоимость» — ставка перевозчика (в т.ч. stoimost'_zak в DOCX).
        if ($template->party === 'carrier' && $this->normalizedPlaceholderIsCarrierContractValue($placeholder)) {
            return 'order.carrier_rate_with_currency';
        }

        // В заказе «тип ТС» не используем; раньше давало «тягач» из флота. Нужен кузов из груза.
        if ($resolved === 'vehicle.transport_type') {
            return 'vehicle.cargo_body_type';
        }

        return $resolved;
    }

    /**
     * Штрафы, нормативы и пеня из мастера заказа ({@see Order::$wizard_state} → financial_term).
     *
     * @return array{client_norms_penalties: array<string, mixed>, carrier_norms_by_leg: list<array<string, mixed>>}
     */
    private function financialNormsPenaltiesSnapshot(Order $order): array
    {
        $wizard = is_array($order->wizard_state) ? $order->wizard_state : [];
        $ft = is_array($wizard['financial_term'] ?? null) ? $wizard['financial_term'] : [];
        $client = is_array($ft['client_norms_penalties'] ?? null) ? $ft['client_norms_penalties'] : [];
        $carrier = is_array($ft['carrier_norms_by_leg'] ?? null) ? $ft['carrier_norms_by_leg'] : [];

        return [
            'client_norms_penalties' => $this->normsPenaltiesRowForPrintSnapshot($client),
            'carrier_norms_by_leg' => array_values(array_map(
                fn (mixed $row): array => $this->normsPenaltiesRowForPrintSnapshot(is_array($row) ? $row : []),
                $carrier,
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normsPenaltiesRowForPrintSnapshot(array $row): array
    {
        $stage = $row['stage'] ?? null;
        $stageOut = is_string($stage) && trim($stage) !== '' ? trim($stage) : null;

        $missAmount = $this->nullableNumericScalar($row['miss_amount'] ?? null);
        $missCurrency = $this->normsPenaltyCurrencyCode($row['miss_currency'] ?? null);
        $downtimeAmount = $this->nullableNumericScalar($row['downtime_amount'] ?? null);
        $downtimeCurrency = $this->normsPenaltyCurrencyCode($row['downtime_currency'] ?? null);
        $fineAmount = $this->nullableNumericScalar($row['fine_amount'] ?? null);
        $fineCurrency = $this->normsPenaltyCurrencyCode($row['fine_currency'] ?? null);

        $penaltyTerms = $row['penalty_terms'] ?? '';
        $penaltyTermsOut = is_string($penaltyTerms) ? trim($penaltyTerms) : '';

        return [
            'stage' => $stageOut,
            'miss_amount' => $missAmount !== null ? $this->formatMoney($missAmount) : null,
            'miss_currency' => $missCurrency,
            'miss_amount_with_currency' => $this->formatMoneyWithCurrency($missAmount, $missCurrency),
            'downtime_amount' => $downtimeAmount !== null ? $this->formatMoney($downtimeAmount) : null,
            'downtime_currency' => $downtimeCurrency,
            'downtime_amount_with_currency' => $this->formatMoneyWithCurrency($downtimeAmount, $downtimeCurrency),
            'fine_amount' => $fineAmount !== null ? $this->formatMoney($fineAmount) : null,
            'fine_currency' => $fineCurrency,
            'fine_amount_with_currency' => $this->formatMoneyWithCurrency($fineAmount, $fineCurrency),
            'penalty_terms' => $penaltyTermsOut === '' ? null : $penaltyTermsOut,
            'norm_loading_hours' => $this->normHoursStringForPrint($row['norm_loading_hours'] ?? null),
            'norm_customs_hours' => $this->normHoursStringForPrint($row['norm_customs_hours'] ?? null),
            'norm_unloading_hours' => $this->normHoursStringForPrint($row['norm_unloading_hours'] ?? null),
        ];
    }

    private function nullableNumericScalar(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normsPenaltyCurrencyCode(mixed $value): string
    {
        if (! is_string($value)) {
            return 'RUB';
        }

        $trimmed = strtoupper(trim($value));

        return $trimmed !== '' ? substr($trimmed, 0, 3) : 'RUB';
    }

    private function normHoursStringForPrint(mixed $value): ?string
    {
        $hours = $this->nullableNumericScalar($value);
        if ($hours === null) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($hours, 2, ',', ' '), '0'), ',');

        return $formatted !== '' ? $formatted : null;
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return $value === null ? null : (string) $value;
        }

        return $value->format('d.m.Y');
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, ',', ' ');
    }

    private function formatMoneyWithCurrency(mixed $value, ?string $currencyCode): ?string
    {
        $money = $this->formatMoney($value);
        if ($money === null) {
            return null;
        }

        $currency = is_string($currencyCode) && trim($currencyCode) !== ''
            ? strtoupper(trim($currencyCode))
            : 'RUB';

        return $money.' '.$currency;
    }

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', ' ');
    }

    private function formatVolumeNumber(float $value): string
    {
        return number_format($value, 3, ',', ' ');
    }
}
