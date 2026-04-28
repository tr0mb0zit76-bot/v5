<?php

namespace App\Services;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Support\CarrierPaymentTermResolver;
use App\Support\PaymentFormCodeLabel;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\PrintFormPlaceholderPathResolver;
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
        $templatePath = Storage::disk($template->file_disk)->path($template->file_path);
        $processor = new TemplateProcessor($templatePath);

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

            $processor->setValue($placeholder, $replacement);
            // Some DOCX templates keep `${ placeholder }` with inner spaces.
            $processor->setValue(' '.$placeholder.' ', $replacement);
        }

        if ($placeholders->isNotEmpty()) {
            $processor->setMacroChars('{{', '}}');

            foreach ($placeholders as $placeholder) {
                if (in_array($placeholder, $overlayPlaceholders, true)) {
                    continue;
                }

                $mappedPath = $this->resolveMappedPath($placeholder, $mapping, $template);
                $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

                $processor->setValue($placeholder, $replacement);
                $processor->setValue(' '.$placeholder.' ', $replacement);
            }
        }

        $overlayStyles = [];
        $overlayTempFiles = [];
        if ($includeTemplateOverlays) {
            $overlayTempFiles = $this->injectTemplateOverlayImages($processor, $template);
            if ($template->shouldApplyCrmOverlayOffsets()) {
                $overlayStyles = $this->buildOverlayFloatingStyles($template);
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

        if ($includeTemplateOverlays && $overlayStyles !== []) {
            $this->applyFloatingImageStyle($absoluteTarget, $overlayStyles);
        }

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'download_name' => $downloadName,
        ];
    }

    /**
     * @param  list<array{margin_left_mm: float, margin_top_mm: float}>  $overlayStyles
     */
    private function applyFloatingImageStyle(string $absoluteDocxPath, array $overlayStyles): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($absoluteDocxPath) !== true) {
            return;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (! is_string($documentXml) || $documentXml === '') {
            $zip->close();

            return;
        }

        $styleIndex = 0;
        $updatedDocumentXml = preg_replace_callback(
            '/<v:shape([^>]*?)style="([^"]*?)"([^>]*)>/',
            static function (array $matches) use ($overlayStyles, &$styleIndex): string {
                $before = $matches[1];
                $style = $matches[2];
                $after = $matches[3];

                if (! str_contains($style, 'position:absolute')) {
                    $style = 'position:absolute;'.$style;
                }

                if (! str_contains($style, 'z-index')) {
                    $style .= ';z-index:251659264';
                }

                if (! str_contains($style, 'mso-wrap-style')) {
                    $style .= ';mso-wrap-style:none';
                }

                // Привязка к странице (а не к абзацу/тексту), иначе при длинном тексте в плейсхолдерах
                // плавающие печать/подпись смещаются вместе с потоком. Gotenberg/LibreOffice рендерит тот же DOCX.
                if (! str_contains($style, 'mso-position-horizontal-relative')) {
                    $style .= ';mso-position-horizontal-relative:page';
                }

                if (! str_contains($style, 'mso-position-vertical-relative')) {
                    $style .= ';mso-position-vertical-relative:page';
                }

                $resolvedOverlayStyle = $overlayStyles[$styleIndex] ?? ['margin_left_mm' => 0.0, 'margin_top_mm' => 0.0];
                $styleIndex++;

                if (! str_contains($style, 'margin-left')) {
                    $style .= ';margin-left:'.number_format((float) $resolvedOverlayStyle['margin_left_mm'], 2, '.', '').'mm';
                }

                if (! str_contains($style, 'margin-top')) {
                    $style .= ';margin-top:'.number_format((float) $resolvedOverlayStyle['margin_top_mm'], 2, '.', '').'mm';
                }

                return '<v:shape'.$before.'style="'.$style.'"'.$after.'>';
            },
            $documentXml
        );

        if (is_string($updatedDocumentXml) && $updatedDocumentXml !== $documentXml) {
            $zip->addFromString('word/document.xml', $updatedDocumentXml);
        }

        $zip->close();
    }

    /**
     * @return list<array{margin_left_mm: float, margin_top_mm: float}>
     */
    private function buildOverlayFloatingStyles(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        return collect(['internal_signature', 'internal_stamp'])
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

        $loadingPoints = $routePoints->where('type', 'loading')->values();
        $unloadingPoints = $routePoints->where('type', 'unloading')->values();
        $fleetSelection = $this->resolvePrimaryFleetSelection($order);
        $driver = $this->driverPayload((int) ($order->driver_id ?? 0), $fleetSelection['fleet_driver_id']);
        $vehicle = $this->vehiclePayload($order, $driver, $fleetSelection['fleet_vehicle_id']);
        $loadingMethod = $this->resolveLoadingMethod($loadingPoints->first(), $order);

        $cargoNames = $cargoItems
            ->map(fn ($cargo): ?string => $cargo->title ?: $cargo->description)
            ->filter()
            ->implode('; ');

        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->weight ?? 0));
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->volume ?? 0));
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
                'customer_payment_form' => $this->resolveCustomerPaymentFormDisplay($order, $paymentTermsPayload),
                'customer_payment_term' => $this->resolveCustomerPaymentTermDisplay($order, $paymentTermsPayload),
                'carrier_payment_form' => $this->resolveCarrierPaymentFormDisplay($order, $paymentTermsPayload),
                'carrier_payment_term' => $this->resolveCarrierPaymentTermDisplay($order, $paymentTermsPayload),
                'invoice_number' => $order->invoice_number,
                'waybill_number' => $order->waybill_number,
                'special_notes' => $order->special_notes,
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
            'carrier' => $this->contractorPayload($order->carrier),
            'own_company' => $this->contractorPayload($order->ownCompany),
            'manager' => [
                'name' => $order->manager?->name,
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
                'loading_cities' => $loadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'loading_first_address' => $this->resolvePointAddress($loadingPoints->first()),
                'loading_first_city' => data_get($loadingPoints->first()?->normalized_data, 'city'),
                'loading_time_from' => $this->resolvePointTimeValue($loadingPoints->first(), 'planned_time_from'),
                'loading_time_to' => $this->resolvePointTimeValue($loadingPoints->first(), 'planned_time_to'),
                'loading_method' => $loadingMethod,
                'loading_types' => $this->resolveLoadingTypes($loadingPoints, $order),
                'unloading_addresses' => $this->resolvePartyAddressList($unloadingPoints),
                'unloading_cities' => $unloadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'unloading_first_address' => $this->resolvePointAddress($unloadingPoints->first()),
                'unloading_first_city' => data_get($unloadingPoints->first()?->normalized_data, 'city'),
                'unloading_time_from' => $this->resolvePointTimeValue($unloadingPoints->first(), 'planned_time_from'),
                'unloading_time_to' => $this->resolvePointTimeValue($unloadingPoints->first(), 'planned_time_to'),
            ],
            'cargo' => array_merge([
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => trim(implode(', ', array_filter([
                        $cargo->title,
                        $cargo->weight !== null ? $this->formatNumber($cargo->weight).' кг' : null,
                        $cargo->volume !== null ? $this->formatNumber($cargo->volume).' м³' : null,
                        $this->cargoDimensionsLabelForCargo($cargo),
                    ]))))
                    ->filter()
                    ->implode('; '),
                'lines_multiline' => $cargoItems
                    ->map(fn ($cargo): string => $this->cargoLineDetailText($cargo))
                    ->filter()
                    ->implode("\n"),
                'names' => $cargoNames,
                'total_weight' => $this->formatNumber($cargoTotalWeight),
                'total_weight_tons' => $this->formatNumber($cargoTotalWeight / 1000),
                'total_volume' => $this->formatNumber($cargoTotalVolume),
                'total_packages' => (string) $cargoTotalPackages,
            ], $this->cargoPerLinePlaceholderMap($cargoItems)),
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
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
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
            return PaymentScheduleSummaryFormatter::format($schedule);
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
     * @return array<string, string|null>
     */
    private function contractorPayload(mixed $contractor): array
    {
        return [
            'name' => $contractor?->name,
            'full_name' => $contractor?->full_name,
            'inn' => $contractor?->inn,
            'kpp' => $contractor?->kpp,
            'ogrn' => $contractor?->ogrn,
            'legal_address' => $contractor?->legal_address,
            'actual_address' => $contractor?->actual_address,
            'phone' => $contractor?->phone,
            'email' => $contractor?->email,
            'contact_person' => $contractor?->contact_person,
            'bank_name' => $contractor?->bank_name,
            'bik' => $contractor?->bik,
            'account_number' => $contractor?->account_number,
            'correspondent_account' => $contractor?->correspondent_account,
            'signer_name_nominative' => $contractor?->signer_name_nominative,
            'signer_name_prepositional' => $contractor?->signer_name_prepositional,
            'signer_position' => $contractor?->contact_person_position,
            'signer_authority_basis' => $contractor?->signer_authority_basis,
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
     * @param  array<string, string|null>  $driver
     * @return array{brand: ?string, number: ?string, transport_type: ?string}
     */
    private function vehiclePayload(Order $order, array $driver, ?int $fleetVehicleId = null): array
    {
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
                    'transport_type' => $this->firstFilledValue([
                        $fleetVehicle->trailer_brand !== null ? 'тягач + полуприцеп' : 'тягач',
                    ]),
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
            'transport_type' => $this->firstFilledValue([
                data_get($driver, 'transport_type'),
                data_get($driver, 'vehicle_type'),
                data_get($orderWizardState, 'vehicle.transport_type'),
                data_get($orderWizardState, 'transport.type'),
                data_get($orderMetadata, 'vehicle.transport_type'),
                data_get($orderMetadata, 'transport_type'),
            ]),
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

        $processor->setMacroChars('${', '}');
        $processor->setImageValue($placeholder, [
            'path' => $absolutePath,
            'width' => $widthPx,
            'height' => $heightPx,
            'ratio' => true,
        ]);
        $processor->setMacroChars('{{', '}}');
        $processor->setImageValue($placeholder, [
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
     * Текстовая строка по одной позиции груза: наименование, вес, объём, габариты (как отдельные плейсхолдеры line_N_text).
     */
    private function cargoLineDetailText(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $parts = array_filter([
            $cargo->title ?: $cargo->description,
            $cargo->weight !== null ? $this->formatNumber((float) $cargo->weight).' кг' : null,
            $cargo->volume !== null ? $this->formatNumber((float) $cargo->volume).' м³' : null,
            $this->cargoDimensionsLabelForCargo($cargo),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return trim(implode(', ', $parts));
    }

    private function cargoDimensionsLabelForCargo(mixed $cargo): ?string
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

        return 'габариты '.$lf.'×'.$wf.'×'.$hf.' м';
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
        }

        return $out;
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

        return '';
    }

    private function resolveMappedPath(string $placeholder, Collection $mapping, PrintFormTemplate $template): string
    {
        $resolved = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'order');

        // Легаси-плейсхолдер stoimost в шаблоне перевозчика должен брать ставку перевозчика.
        if (mb_strtolower(trim($placeholder)) === 'stoimost' && $template->party === 'carrier') {
            return 'order.carrier_rate';
        }

        return $resolved;
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

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', ' ');
    }
}
