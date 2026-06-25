<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Support\CargoPackagesLabelFormatter;
use App\Support\CarrierNormsPenaltiesForPrintContext;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CarrierPortalSubmission;
use App\Support\ContractorPrimaryContactResolver;
use App\Support\DocxPrintFormPlaceholderPreprocessor;
use App\Support\DocxTextRunPlaceholderMerger;
use App\Support\DocxVmlOverlayStylePatcher;
use App\Support\OrderPrintFormContext;
use App\Support\PaymentFormCodeLabel;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\PhpWordTemplateOverlayImageInjector;
use App\Support\PrintFormBasicTermsTableCloner;
use App\Support\PrintFormCargoScopeResolver;
use App\Support\PrintFormCargoTableCloner;
use App\Support\PrintFormImageOverlayPlaceholders;
use App\Support\PrintFormPlaceholderMacroVariants;
use App\Support\PrintFormPlaceholderPathResolver;
use App\Support\PrintFormRoutePointTableCloner;
use App\Support\PrintFormRouteTableCloner;
use App\Support\PrintFormTemplateDiskSource;
use App\Support\PrintFormTemplateProcessorPreparer;
use App\Support\PrintFormVerificationQrDimensions;
use App\Support\RussianGivenName;
use App\Support\RussianPositionInflector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use TCPDF2DBarcode;

class OrderPrintFormDraftService
{
    private const QR_IMAGE_PLACEHOLDER = 'document_verification_qr';

    /** @var list<string> */
    private const RESERVED_PREPROCESS_MACROS = [
        'document_verification_code',
        'document_verification_qr',
    ];

    public static function isVerificationQrPlaceholder(string $placeholder): bool
    {
        $base = trim(explode('#', trim($placeholder))[0]);

        return $base === self::QR_IMAGE_PLACEHOLDER;
    }

    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
        private readonly PrintFormPlaceholderPathResolver $placeholderPathResolver,
        private readonly PrintFormBasicTermsService $basicTermsService,
    ) {}

    /**
     * @return array{disk: string, path: string, download_name: string, verification_qr_injected: bool}
     */
    public function generate(
        PrintFormTemplate $template,
        Order $order,
        bool $includeTemplateOverlays = true,
        ?OrderPrintFormContext $context = null,
        bool $applyVmlOverlayPatch = true,
    ): array {
        $templatePrep = PrintFormTemplateDiskSource::ensureMutableTempCopy(
            PrintFormTemplateDiskSource::prepareLocalPathForPhpWord($template->file_disk, $template->file_path),
        );

        $settings = is_array($template->settings) ? $template->settings : [];
        $placeholders = collect($settings['variables'] ?? [])
            ->merge($this->placeholderExtractor->extractFromDisk($template->file_disk, $template->file_path))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();

        $placeholderNames = $placeholders
            ->merge(self::RESERVED_PREPROCESS_MACROS)
            ->unique()
            ->values()
            ->all();

        DocxPrintFormPlaceholderPreprocessor::preprocess($templatePrep['path'], $placeholderNames);

        try {
            $processor = new TemplateProcessor($templatePrep['path']);
        } finally {
            foreach ($templatePrep['tempFiles'] as $tmpPath) {
                if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        $mapping = collect($settings['variable_mapping'] ?? []);
        $orderForSnapshot = $this->loadOrderContext($order);
        $context = $this->normalizePrintContext($orderForSnapshot, $context);
        $snapshot = $this->buildSnapshot($orderForSnapshot, $context);
        $overlayPlaceholders = $this->overlayPlaceholderList($template);
        $cargoItems = $orderForSnapshot->relationLoaded('cargoItems') ? $orderForSnapshot->cargoItems : collect();

        $processor->setMacroChars('${', '}');

        PrintFormTemplateProcessorPreparer::repairTextMacros(
            $processor,
            $placeholderNames,
        );

        (new PrintFormCargoTableCloner)->apply(
            $processor,
            $this->buildCargoTableRowsForTemplate($cargoItems, $orderForSnapshot, $context),
        );

        (new PrintFormRouteTableCloner)->apply(
            $processor,
            $this->buildRouteTableRowsForTemplate($orderForSnapshot, $context),
        );

        (new PrintFormRoutePointTableCloner)->apply(
            $processor,
            $this->buildRoutePointTableRowsForTemplate($orderForSnapshot, $context),
        );

        $this->applyBasicTermsTables($processor, $orderForSnapshot, $template, $context, $placeholders);

        $qrVmlShapeSkipCount = $this->countVerificationQrVmlShapes($processor, $placeholders, $context);
        $qrTempFiles = $this->injectVerificationQrImage($processor, $placeholders, $context);
        $verificationQrInjected = $qrTempFiles !== [];

        foreach ($placeholders as $placeholder) {
            if (in_array($placeholder, $overlayPlaceholders, true)) {
                continue;
            }

            if (PrintFormCargoTableCloner::isCargoTablePlaceholder($placeholder)) {
                continue;
            }

            if (PrintFormRouteTableCloner::isRouteTablePlaceholder($placeholder)) {
                continue;
            }

            if (PrintFormRoutePointTableCloner::isRoutePointTablePlaceholder($placeholder)) {
                continue;
            }

            if (PrintFormBasicTermsTableCloner::isBasicTermsPlaceholder($placeholder)) {
                continue;
            }

            if (self::isVerificationQrPlaceholder($placeholder)) {
                continue;
            }

            $mappedPath = $this->resolveMappedPath($placeholder, $mapping, $template);
            $replacement = $this->resolvePlaceholderReplacement($placeholder, $placeholders, $mapping, $template, $snapshot);

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

                if (PrintFormCargoTableCloner::isCargoTablePlaceholder($placeholder)) {
                    continue;
                }

                if (PrintFormRouteTableCloner::isRouteTablePlaceholder($placeholder)) {
                    continue;
                }

                if (PrintFormRoutePointTableCloner::isRoutePointTablePlaceholder($placeholder)) {
                    continue;
                }

                if (PrintFormBasicTermsTableCloner::isBasicTermsPlaceholder($placeholder)) {
                    continue;
                }

                if (self::isVerificationQrPlaceholder($placeholder)) {
                    continue;
                }

                $replacement = $this->resolvePlaceholderReplacement($placeholder, $placeholders, $mapping, $template, $snapshot);

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

        foreach ($qrTempFiles as $tmpPath) {
            if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        if ($applyVmlOverlayPatch) {
            DocxVmlOverlayStylePatcher::patchDocx(
                $absoluteTarget,
                $overlayStyles,
                true,
                $verificationQrInjected ? $qrVmlShapeSkipCount : 0,
            );
        }

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'download_name' => $downloadName,
            'verification_qr_injected' => $verificationQrInjected,
        ];
    }

    /**
     * @return list<array{margin_left_mm: float, margin_top_mm: float}>
     */
    private function buildOverlayFloatingStyles(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $keys = PrintFormImageOverlayPlaceholders::activeOverlayKeysInReadingOrder($template);

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
    private function buildSnapshot(Order $order, ?OrderPrintFormContext $context = null): array
    {
        /** @var Collection<int, mixed> $routePoints */
        $routePoints = $order->relationLoaded('routePoints') ? $order->routePoints : collect();
        $routePoints = $this->filterRoutePointsForPrintContext($order, $routePoints, $context);
        /** @var Collection<int, mixed> $cargoItems */
        $cargoItems = $order->relationLoaded('cargoItems') ? $order->cargoItems : collect();

        $loadingPoints = $routePoints->where('type', 'loading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $unloadingPoints = $routePoints->where('type', 'unloading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $portalSubmission = $this->resolveCarrierPortalSubmission($order, $context);
        $fleetSelection = $this->resolvePrimaryFleetSelection($order, $context);
        $driver = $this->driverPayload((int) ($order->driver_id ?? 0), $fleetSelection['fleet_driver_id'], $portalSubmission);
        $vehicle = $this->vehiclePayload($order, $driver, $fleetSelection['fleet_vehicle_id'], $cargoItems, $portalSubmission);
        $loadingMethod = $this->resolveLoadingMethod($loadingPoints->first(), $order);

        $cargoNames = $cargoItems
            ->map(fn ($cargo): ?string => $cargo->title ?: $cargo->description)
            ->filter()
            ->implode('; ');

        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => $this->cargoPrintMetrics(
            $cargo,
            PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context),
        )['total_weight_kg']);
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => $this->cargoPrintMetrics(
            $cargo,
            PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context),
        )['total_volume_m3']);
        $cargoTotalPackages = $cargoItems->sum(fn ($cargo): int => $this->cargoPrintMetrics(
            $cargo,
            PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context),
        )['package_count']);

        $paymentTermsPayload = $this->decodeOrderPaymentTermsPayload($order);
        $scopedPaymentTermsPayload = $this->paymentTermsPayloadForPrintContext($order, $paymentTermsPayload, $context);
        $carrierContractor = $this->resolveCarrierContractorForPrint($order, $context);
        $this->ensureContractorContactsLoaded($order->client);
        $this->ensureContractorContactsLoaded($carrierContractor instanceof Contractor ? $carrierContractor : null);

        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => $this->formatDate($order->order_date),
                'loading_date' => $this->formatDate($order->loading_date),
                'unloading_date' => $this->formatDate($order->unloading_date),
                'status' => $order->status,
                'customer_rate' => $this->formatMoney($order->customer_rate),
                'carrier_rate' => $this->formatMoney($this->resolveCarrierRateValue($order, $context)),
                'customer_rate_with_currency' => $this->formatMoneyWithCurrency(
                    $order->customer_rate,
                    $this->resolveCustomerCurrencyCode($order, $paymentTermsPayload),
                ),
                'carrier_rate_with_currency' => $this->formatMoneyWithCurrency(
                    $this->resolveCarrierRateValue($order, $context),
                    $this->resolveCarrierCurrencyCode($order, $scopedPaymentTermsPayload),
                ),
                'customer_payment_form' => $this->resolveCustomerPaymentFormDisplay($order, $paymentTermsPayload),
                'customer_payment_term' => $this->resolveCustomerPaymentTermDisplay($order, $paymentTermsPayload),
                'carrier_payment_form' => $this->resolveCarrierPaymentFormDisplay($order, $scopedPaymentTermsPayload),
                'carrier_payment_term' => $this->resolveCarrierPaymentTermDisplay($order, $scopedPaymentTermsPayload),
                'invoice_number' => $order->invoice_number,
                'waybill_number' => $order->waybill_number,
                'special_notes' => $order->special_notes,
                'svh_name' => $order->svh_name,
                'svh_address' => $order->svh_address,
                'customs_post_code' => $order->customs_post_code,
                'customs_post_name' => null,
                'customs_declaration_place' => null,
                'customs_commodity_code' => null,
                'cargo_declared_sum' => $this->formatMoney(
                    Schema::hasColumn('orders', 'cargo_declared_sum') ? $order->cargo_declared_sum : null,
                ),
                'svh_summary' => $this->formatSvhSummaryBlock($order),
            ],
            'cargo_sender' => [
                'name' => $this->resolvePrimaryPartyValue($loadingPoints, 'sender_name'),
                'address' => $this->resolvePrimaryAddressValue($loadingPoints),
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
                'contact' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'phone' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'contact_phone' => $this->resolvePrimaryPartyContactPhone($unloadingPoints, 'recipient_contact', 'recipient_phone'),
                'all_names' => $this->resolvePartyList($unloadingPoints, 'recipient_name'),
                'all_addresses' => $this->resolvePartyAddressList($unloadingPoints),
                'all_contact_phones' => $this->resolvePartyContactPhoneList($unloadingPoints, 'recipient_contact', 'recipient_phone'),
            ],
            'customer' => $this->contractorPayload($order->client),
            'carrier' => $this->contractorPayload($carrierContractor),
            'own_company' => $this->contractorPayload($order->ownCompany, $order->own_company_bank_account_id),
            'manager' => $this->managerPayload($order->manager),
            'responsible' => $this->managerPayload($order->manager),
            'driver' => $driver,
            'vehicle' => $vehicle,
            'contacts' => $this->partyContactsPayload($order, $order->client, $carrierContractor),
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
                'loading_special_conditions' => $this->resolvePerformerSpecialConditions($order, $context, 'loading_special_conditions'),
                'unloading_special_conditions' => $this->resolvePerformerSpecialConditions($order, $context, 'unloading_special_conditions'),
            ],
            'cargo' => array_merge([
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => $this->cargoLineDetailTextForSummaryLine(
                        $cargo,
                        PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context),
                    ))
                    ->filter(fn (string $s): bool => $s !== '')
                    ->implode('  |  '),
                'lines_multiline' => $cargoItems
                    ->map(fn ($cargo): string => $this->cargoLineDetailText(
                        $cargo,
                        PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context),
                    ))
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
            ], $this->cargoPerLinePlaceholderMap($cargoItems, $order, $context)),
            'document_verification_code' => $context?->documentVerificationCode ?? '',
            'financial' => $this->financialNormsPenaltiesSnapshot($order, $context),
        ];
    }

    /**
     * Сколько VML-картинок создаст QR — их нельзя учитывать при смещении подписи/печати.
     *
     * @param  Collection<int, string>  $placeholders
     */
    private function countVerificationQrVmlShapes(
        TemplateProcessor $processor,
        Collection $placeholders,
        ?OrderPrintFormContext $context,
    ): int {
        $code = $context?->documentVerificationCode;
        if ($code === null || $code === '') {
            return 0;
        }

        $orderDocumentId = (int) ($context?->orderDocumentId ?? 0);
        if ($orderDocumentId <= 0) {
            return 0;
        }

        $this->repairVerificationQrMacroInProcessor($processor);

        if (
            ! $placeholders->contains(self::QR_IMAGE_PLACEHOLDER)
            && ! $this->processorHasVerificationQrMacro($processor)
        ) {
            return 0;
        }

        $total = 0;

        foreach ([['${', '}'], ['{{', '}}']] as [$open, $close]) {
            $processor->setMacroChars($open, $close);
            DocxTextRunPlaceholderMerger::applyToTemplateProcessor(
                $processor,
                $open,
                $close,
                self::QR_IMAGE_PLACEHOLDER,
            );
            $total += PhpWordTemplateOverlayImageInjector::countPlaceholderMacros(
                $processor,
                self::QR_IMAGE_PLACEHOLDER,
            );
        }

        $processor->setMacroChars('${', '}');

        return $total;
    }

    /**
     * Если в шаблоне есть плейсхолдер document_verification_qr и код проверки не пуст —
     * генерирует PNG QR-кода и вставляет через setImageValue.
     *
     * @param  Collection<int, string>  $placeholders
     * @return list<string> временные файлы для очистки после saveAs
     */
    private function injectVerificationQrImage(
        TemplateProcessor $processor,
        Collection $placeholders,
        ?OrderPrintFormContext $context,
    ): array {
        $code = $context?->documentVerificationCode;
        if ($code === null || $code === '') {
            return [];
        }

        $this->repairVerificationQrMacroInProcessor($processor);

        if (
            ! $placeholders->contains(self::QR_IMAGE_PLACEHOLDER)
            && ! $this->processorHasVerificationQrMacro($processor)
        ) {
            return [];
        }

        $orderDocumentId = (int) ($context?->orderDocumentId ?? 0);
        if ($orderDocumentId <= 0) {
            return [];
        }

        $url = route('print-verification.order-documents.show', [
            'orderDocument' => $orderDocumentId,
            'code' => $code,
        ]);

        try {
            if (! class_exists(TCPDF2DBarcode::class)) {
                Log::warning('docx.verification_qr_inject_failed', [
                    'message' => 'TCPDF2DBarcode is not available. Run composer install (tecnickcom/tcpdf).',
                ]);

                return [];
            }

            $pixelSize = PrintFormVerificationQrDimensions::pngPixelSize();
            $qr = new TCPDF2DBarcode($url, 'QRCODE,H');
            $qrPng = $qr->getBarcodePngData($pixelSize, $pixelSize, [0, 0, 0]);
            if (! is_string($qrPng) || $qrPng === '') {
                return [];
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'crm-docx-qr-');
            if ($tmpFile === false) {
                return [];
            }

            @unlink($tmpFile);
            $tmpPath = $tmpFile.'.png';
            if (file_put_contents($tmpPath, $qrPng) === false) {
                return [];
            }

            PhpWordTemplateOverlayImageInjector::injectImageForAllMacroStyles($processor, self::QR_IMAGE_PLACEHOLDER, [
                'path' => $tmpPath,
                'width' => PrintFormVerificationQrDimensions::docxWidthPx(),
                'height' => PrintFormVerificationQrDimensions::docxHeightPx(),
                'ratio' => true,
            ]);

            return [$tmpPath];
        } catch (\Throwable $e) {
            Log::warning('docx.verification_qr_inject_failed', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function repairVerificationQrMacroInProcessor(TemplateProcessor $processor): void
    {
        foreach ([['${', '}'], ['{{', '}}']] as [$open, $close]) {
            DocxTextRunPlaceholderMerger::applyToTemplateProcessor(
                $processor,
                $open,
                $close,
                self::QR_IMAGE_PLACEHOLDER,
            );
        }
    }

    private function processorHasVerificationQrMacro(TemplateProcessor $processor): bool
    {
        if (PhpWordTemplateOverlayImageInjector::countPlaceholderMacros($processor, self::QR_IMAGE_PLACEHOLDER) > 0) {
            return true;
        }

        foreach ($processor->getVariables() as $variable) {
            if (self::isVerificationQrPlaceholder((string) $variable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{fleet_vehicle_id:int|null,fleet_driver_id:int|null}
     */
    private function resolvePrimaryFleetSelection(Order $order, ?OrderPrintFormContext $context = null): array
    {
        foreach ($this->collectPerformerRows($order) as $performer) {
            if (! $this->performerMatchesPrintContext($order, $performer, $context)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $selection = $this->extractFleetIdsFromRow($slot);
                    if ($selection['fleet_vehicle_id'] !== null || $selection['fleet_driver_id'] !== null) {
                        return $selection;
                    }
                }

                continue;
            }

            $selection = $this->extractFleetIdsFromRow($performer);
            if ($selection['fleet_vehicle_id'] !== null || $selection['fleet_driver_id'] !== null) {
                return $selection;
            }
        }

        return [
            'fleet_vehicle_id' => null,
            'fleet_driver_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{fleet_vehicle_id:int|null,fleet_driver_id:int|null}
     */
    private function extractFleetIdsFromRow(array $row): array
    {
        $vehicleId = isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null && $row['fleet_vehicle_id'] !== ''
            ? (int) $row['fleet_vehicle_id']
            : null;
        $driverId = isset($row['fleet_driver_id']) && $row['fleet_driver_id'] !== null && $row['fleet_driver_id'] !== ''
            ? (int) $row['fleet_driver_id']
            : null;

        if ($vehicleId === null) {
            $tripId = isset($row['fleet_trip_id']) && $row['fleet_trip_id'] !== null && $row['fleet_trip_id'] !== ''
                ? (int) $row['fleet_trip_id']
                : null;

            if ($tripId !== null) {
                $fromTrip = $this->resolveFleetIdsFromTrip($tripId);
                $vehicleId = $fromTrip['fleet_vehicle_id'];
                if ($driverId === null) {
                    $driverId = $fromTrip['fleet_driver_id'];
                }
            }
        }

        return [
            'fleet_vehicle_id' => $vehicleId,
            'fleet_driver_id' => $driverId,
        ];
    }

    /**
     * @return array{fleet_vehicle_id:int|null,fleet_driver_id:int|null}
     */
    private function resolveFleetIdsFromTrip(int $fleetTripId): array
    {
        if (! Schema::hasTable('fleet_trips')) {
            return [
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
            ];
        }

        /** @var FleetTrip|null $trip */
        $trip = FleetTrip::query()->find($fleetTripId);

        if ($trip === null) {
            return [
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
            ];
        }

        return [
            'fleet_vehicle_id' => $trip->fleet_vehicle_id !== null ? (int) $trip->fleet_vehicle_id : null,
            'fleet_driver_id' => $trip->fleet_driver_id !== null ? (int) $trip->fleet_driver_id : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCarrierPortalSubmission(Order $order, ?OrderPrintFormContext $context = null): ?array
    {
        foreach ($this->collectPerformerRows($order) as $performer) {
            if (! $this->performerMatchesPrintContext($order, $performer, $context)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $submission = $slot['carrier_portal_submission'] ?? null;
                    if (CarrierPortalSubmission::isUsable(is_array($submission) ? $submission : null)) {
                        return $submission;
                    }
                }

                continue;
            }

            $submission = $performer['carrier_portal_submission'] ?? null;
            if (CarrierPortalSubmission::isUsable(is_array($submission) ? $submission : null)) {
                return $submission;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectPerformerRows(Order $order): array
    {
        $rows = [];

        if ($order->relationLoaded('legs')) {
            foreach ($order->legs->sortBy('sequence') as $leg) {
                $performer = is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : null;
                if (is_array($performer)) {
                    $rows[] = $performer;
                }
            }
        }

        foreach (is_array($order->performers) ? $order->performers : [] as $performer) {
            if (is_array($performer)) {
                $rows[] = $performer;
            }
        }

        if ($rows === []) {
            $wizardPerformers = data_get($order->wizard_state, 'performers');
            foreach (is_array($wizardPerformers) ? $wizardPerformers : [] as $performer) {
                if (is_array($performer)) {
                    $rows[] = $performer;
                }
            }
        }

        return $rows;
    }

    private function resolveCarrierRateValue(Order $order, ?OrderPrintFormContext $context = null): ?float
    {
        if ($this->printContextScopesCarrierFinancials($context)) {
            $fromCosts = $this->sumCarrierAmountFromContractorsCosts($order, $context);
            if ($fromCosts !== null) {
                return $fromCosts;
            }

            $fromLegs = $this->sumCarrierAmountFromLegCosts($order, $context);

            return $fromLegs;
        }

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

        $costs = $this->contractorsCostsRows($order);
        if ($costs !== []) {
            $sumFromCosts = collect($costs)->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
            if ($sumFromCosts > 0) {
                return $sumFromCosts;
            }
        }

        return null;
    }

    private function printContextScopesCarrierFinancials(?OrderPrintFormContext $context): bool
    {
        return $context !== null
            && (
                ($context->carrierContractorId !== null && $context->carrierContractorId > 0)
                || ($context->legStage !== null && $context->legStage !== '')
            );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractorsCostsRows(Order $order): array
    {
        if ($order->relationLoaded('financialTerms')) {
            $costs = $order->financialTerms->first()?->contractors_costs;
            if (is_array($costs) && $costs !== []) {
                return $costs;
            }
        }

        $wizard = is_array($order->wizard_state) ? $order->wizard_state : [];
        $wizardCosts = data_get($wizard, 'financial_term.contractors_costs');

        return is_array($wizardCosts) ? $wizardCosts : [];
    }

    /**
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    private function filterContractorsCostsForPrintContext(array $costs, ?OrderPrintFormContext $context): array
    {
        if (! $this->printContextScopesCarrierFinancials($context)) {
            return $costs;
        }

        return collect($costs)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->filter(function (array $cost) use ($context): bool {
                if ($context->carrierContractorId !== null && $context->carrierContractorId > 0) {
                    $contractorId = isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                        ? (int) $cost['contractor_id']
                        : 0;

                    if ($contractorId !== $context->carrierContractorId) {
                        return false;
                    }
                }

                if ($context->legStage !== null && $context->legStage !== '') {
                    $costStage = $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1'));
                    $ctxStage = $this->normalizeStageIdentifier($context->legStage);

                    if ($costStage !== $ctxStage) {
                        return false;
                    }
                }

                if ($context->carrierSlot !== null && $context->carrierSlot > 0) {
                    $costSlot = isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                        ? (int) $cost['carrier_slot']
                        : null;

                    if ($costSlot !== $context->carrierSlot) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }

    private function sumCarrierAmountFromContractorsCosts(Order $order, ?OrderPrintFormContext $context): ?float
    {
        $filtered = $this->filterContractorsCostsForPrintContext($this->contractorsCostsRows($order), $context);

        if ($filtered === []) {
            return null;
        }

        return collect($filtered)->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
    }

    private function sumCarrierAmountFromLegCosts(Order $order, ?OrderPrintFormContext $context): ?float
    {
        if ($context === null || ! $order->relationLoaded('legs') || ! Schema::hasTable('leg_costs')) {
            return null;
        }

        $legs = $order->legs;

        if ($context->legStage !== null && $context->legStage !== '') {
            $legId = $this->resolveLegIdForStage($order, $context->legStage);
            if ($legId === null) {
                return null;
            }

            $legs = $legs->where('id', $legId);
        } elseif ($context->carrierContractorId !== null && $context->carrierContractorId > 0) {
            $legIds = $this->legIdsForCarrierContractor($order, $context->carrierContractorId);
            if ($legIds === []) {
                return null;
            }

            $legs = $legs->whereIn('id', $legIds);
        } else {
            return null;
        }

        if ($legs->isEmpty()) {
            return null;
        }

        return $legs->sum(fn ($leg): float => (float) ($leg->cost?->amount ?? 0));
    }

    /**
     * @param  array<string, mixed>|null  $paymentTermsPayload
     * @return array<string, mixed>|null
     */
    private function paymentTermsPayloadForPrintContext(
        Order $order,
        ?array $paymentTermsPayload,
        ?OrderPrintFormContext $context,
    ): ?array {
        if ($paymentTermsPayload === null || ! $this->printContextScopesCarrierFinancials($context)) {
            return $paymentTermsPayload;
        }

        $filtered = $this->filterContractorsCostsForPrintContext($this->contractorsCostsRows($order), $context);
        if ($filtered === []) {
            return $paymentTermsPayload;
        }

        $payload = $paymentTermsPayload;
        $payload['carriers'] = collect($filtered)
            ->map(function (array $cost): array {
                $schedule = $cost['payment_schedule'] ?? [];
                if (! is_array($schedule)) {
                    $schedule = [];
                }

                return [
                    'stage' => $cost['stage'] ?? null,
                    'carrier_slot' => isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                        ? (int) $cost['carrier_slot']
                        : null,
                    'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null
                        ? (int) $cost['contractor_id']
                        : null,
                    'amount' => isset($cost['amount']) ? (float) $cost['amount'] : null,
                    'payment_form' => $cost['payment_form'] ?? null,
                    'currency' => $cost['currency'] ?? null,
                    'payment_terms' => $cost['payment_terms'] ?? null,
                    'payment_schedule' => $schedule,
                ];
            })
            ->values()
            ->all();

        return $payload;
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

            if (! isset($fromCostsOnly['carriers']) || $fromCostsOnly['carriers'] === []) {
                return null;
            }

            return $this->mergeClientContractPrintSummaryIntoPaymentTermsPayload($order, $fromCostsOnly);
        }

        if (is_array($raw)) {
            return $this->mergeClientContractPrintSummaryIntoPaymentTermsPayload(
                $order,
                $this->mergeCarriersFromFinancialTermsIfMissing($order, $raw),
            );
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            ? $this->mergeClientContractPrintSummaryIntoPaymentTermsPayload(
                $order,
                $this->mergeCarriersFromFinancialTermsIfMissing($order, $decoded),
            )
            : null;
    }

    /**
     * Текст «Сводка для договора и печати» хранится в {@see FinancialTerm::$client_payment_terms} и в JSON как {@code client.payment_terms_text}.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function mergeClientContractPrintSummaryIntoPaymentTermsPayload(Order $order, array $decoded): array
    {
        $client = is_array($decoded['client'] ?? null) ? $decoded['client'] : [];

        if (Schema::hasTable('financial_terms') && $order->relationLoaded('financialTerms')) {
            $fromFt = trim((string) ($order->financialTerms->first()?->client_payment_terms ?? ''));
            if ($fromFt !== '') {
                $client['payment_terms_text'] = $fromFt;
                $decoded['client'] = $client;

                return $decoded;
            }
        }

        $existing = trim((string) data_get($decoded, 'client.payment_terms_text', ''));
        if ($existing !== '') {
            return $decoded;
        }

        return $decoded;
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
    private function resolveCarrierContractorForPrint(Order $order, ?OrderPrintFormContext $context = null): mixed
    {
        if ($context?->carrierContractorId !== null) {
            return Contractor::query()->find($context->carrierContractorId) ?? $order->carrier;
        }

        if ($order->carrier) {
            return $order->carrier;
        }

        $contractorId = $this->firstCarrierContractorIdFromFinancialTerms($order);
        if ($contractorId !== null) {
            return Contractor::query()->find($contractorId) ?? $order->carrier;
        }

        return $order->carrier;
    }

    private function resolvePerformerSpecialConditions(Order $order, ?OrderPrintFormContext $context, string $field): ?string
    {
        $values = [];

        foreach ($this->collectPerformerRows($order) as $performer) {
            if (! $this->performerMatchesPrintContext($order, $performer, $context)) {
                continue;
            }

            $value = trim((string) ($performer[$field] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? null : implode("\n\n", $values);
    }

    private function resolveStageKeyForRoutePoint(Order $order, RoutePoint $point): string
    {
        $legId = (int) ($point->order_leg_id ?? 0);
        if ($legId > 0 && $order->relationLoaded('legs')) {
            $leg = $order->legs->firstWhere('id', $legId);
            if ($leg !== null) {
                return $this->normalizeStageIdentifier((string) $leg->description);
            }
        }

        return 'leg_1';
    }

    private function resolveSpecialConditionsForRoutePointRow(
        Order $order,
        ?OrderPrintFormContext $context,
        string $stageKey,
        string $type,
    ): string {
        if (! in_array($type, ['loading', 'unloading'], true)) {
            return '';
        }

        $field = $type === 'loading' ? 'loading_special_conditions' : 'unloading_special_conditions';
        $normalizedStage = $this->normalizeStageIdentifier($stageKey);

        foreach ($this->collectPerformerRows($order) as $performer) {
            if ($this->normalizeStageIdentifier((string) ($performer['stage'] ?? '')) !== $normalizedStage) {
                continue;
            }

            if (! $this->performerMatchesPrintContext($order, $performer, $context)) {
                continue;
            }

            return trim((string) ($performer[$field] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $performer
     */
    private function performerMatchesPrintContext(Order $order, array $performer, ?OrderPrintFormContext $context): bool
    {
        if ($context === null) {
            return true;
        }

        $stage = $this->normalizeStageIdentifier((string) ($performer['stage'] ?? ''));

        if (
            $context->legStage !== null
            && $context->legStage !== ''
            && ! $context->routeLegsAsTableRows
        ) {
            return $stage === $this->normalizeStageIdentifier($context->legStage);
        }

        if ($context->carrierContractorId !== null) {
            $legId = $this->resolveLegIdForStage($order, $stage);
            if ($legId !== null && $order->relationLoaded('legs')) {
                $leg = $order->legs->firstWhere('id', $legId);
                if ($leg instanceof OrderLeg) {
                    return $this->legBelongsToCarrier($leg, $context->carrierContractorId);
                }
            }

            return $this->performerBelongsToCarrier($performer, $context->carrierContractorId);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $performer
     */
    private function performerBelongsToCarrier(array $performer, int $carrierContractorId): bool
    {
        if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
            foreach ($performer['split_carriers'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                if ((int) ($slot['contractor_id'] ?? 0) === $carrierContractorId) {
                    return true;
                }
            }

            return false;
        }

        return (int) ($performer['contractor_id'] ?? 0) === $carrierContractorId;
    }

    /**
     * @param  Collection<int, mixed>  $routePoints
     * @return Collection<int, mixed>
     */
    private function filterRoutePointsForPrintContext(Order $order, Collection $routePoints, ?OrderPrintFormContext $context): Collection
    {
        if ($context === null || $context->routeLegsAsTableRows) {
            return $routePoints;
        }

        if ($context->legStage !== null && $context->legStage !== '') {
            $legId = $this->resolveLegIdForStage($order, $context->legStage);
            if ($legId === null) {
                return $routePoints;
            }

            return $routePoints->where('order_leg_id', $legId)->values();
        }

        if ($context->carrierContractorId !== null) {
            $legIds = $this->legIdsForCarrierContractor($order, $context->carrierContractorId);
            if ($legIds === []) {
                return $routePoints;
            }

            return $routePoints->whereIn('order_leg_id', $legIds)->values();
        }

        return $routePoints;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildRouteTableRowsForTemplate(Order $order, ?OrderPrintFormContext $context): array
    {
        if ($context === null || ! $context->routeLegsAsTableRows) {
            return [];
        }

        if (! $order->relationLoaded('legs')) {
            return [];
        }

        $carrierId = $context->carrierContractorId;
        $legs = $order->legs->sortBy('sequence')->values();
        $rows = [];
        $index = 0;

        foreach ($legs as $leg) {
            if ($carrierId !== null && ! $this->legBelongsToCarrier($leg, $carrierId)) {
                continue;
            }

            $points = $leg->relationLoaded('routePoints')
                ? $leg->routePoints->sortBy('sequence')
                : collect();
            $loading = $points->where('type', 'loading')->values();
            $unloading = $points->where('type', 'unloading')->values();
            $stageLabel = $this->formatLegStageLabel((string) $leg->description);
            $loadingAddresses = $this->resolvePartyAddressList($loading);
            $unloadingAddresses = $this->resolvePartyAddressList($unloading);
            $loadingCities = $this->resolvePointCityList($loading);
            $unloadingCities = $this->resolvePointCityList($unloading);
            $summaryParts = array_filter([
                $stageLabel !== '' ? 'Плечо: '.$stageLabel : null,
                $loadingAddresses !== null && $loadingAddresses !== '' ? 'Загрузка: '.$loadingAddresses : null,
                $unloadingAddresses !== null && $unloadingAddresses !== '' ? 'Выгрузка: '.$unloadingAddresses : null,
            ]);

            $index++;
            $rows[] = [
                'route_row_index' => (string) $index,
                'route_row_stage' => $stageLabel,
                'route_row_loading_addresses' => $loadingAddresses ?? '',
                'route_row_unloading_addresses' => $unloadingAddresses ?? '',
                'route_row_loading_cities' => $loadingCities ?? '',
                'route_row_unloading_cities' => $unloadingCities ?? '',
                'route_row_summary' => implode("\n", $summaryParts),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildRoutePointTableRowsForTemplate(Order $order, ?OrderPrintFormContext $context): array
    {
        /** @var Collection<int, mixed> $routePoints */
        $routePoints = $order->relationLoaded('routePoints') ? $order->routePoints : collect();
        $routePoints = $this->filterRoutePointsForPrintContext($order, $routePoints, $context)
            ->sortBy(fn ($point): int => (int) ($point->sequence ?? 0))
            ->values();

        if ($routePoints->isEmpty()) {
            return [];
        }

        $legStageLabels = [];
        if ($order->relationLoaded('legs')) {
            foreach ($order->legs as $leg) {
                $legStageLabels[(int) $leg->id] = $this->formatLegStageLabel((string) $leg->description);
            }
        }

        $rows = [];
        $index = 0;

        foreach ($routePoints as $point) {
            $type = strtolower(trim((string) ($point->type ?? '')));
            if (! in_array($type, ['loading', 'unloading'], true)) {
                continue;
            }

            $address = $this->resolvePointAddress($point) ?? '';
            if ($address === '') {
                continue;
            }

            $city = $this->resolvePointCity($point) ?? '';
            $partyName = $type === 'loading'
                ? trim((string) ($point->sender_name ?? ''))
                : trim((string) ($point->recipient_name ?? ''));
            $contactPhone = $type === 'loading'
                ? ($this->buildContactPhoneValue($point->sender_contact ?? null, $point->sender_phone ?? null) ?? '')
                : ($this->buildContactPhoneValue($point->recipient_contact ?? null, $point->recipient_phone ?? null) ?? '');
            $typeLabel = $type === 'loading' ? 'Погрузка' : 'Выгрузка';
            $stageLabel = $legStageLabels[(int) ($point->order_leg_id ?? 0)] ?? '';
            $stageKey = $this->resolveStageKeyForRoutePoint($order, $point);
            $specialConditions = $this->resolveSpecialConditionsForRoutePointRow($order, $context, $stageKey, $type);
            $plannedDate = $point->planned_date?->format('d.m.Y') ?? '';
            $timeRange = $this->resolvePointTimeRange($point) ?? '';
            $summaryParts = array_filter([
                $typeLabel,
                $city !== '' ? $city : null,
                $address !== '' ? $address : null,
                $partyName !== '' ? $partyName : null,
                $contactPhone !== '' ? $contactPhone : null,
                $plannedDate !== '' ? $plannedDate : null,
                $timeRange !== '' ? $timeRange : null,
                $specialConditions !== '' ? $specialConditions : null,
            ]);

            $index++;
            $rows[] = [
                'route_point_row_index' => (string) $index,
                'route_point_row_stage' => $stageLabel,
                'route_point_row_type' => $type,
                'route_point_row_type_label' => $typeLabel,
                'route_point_row_city' => $city,
                'route_point_row_address' => $address,
                'route_point_row_party_name' => $partyName,
                'route_point_row_contact_phone' => $contactPhone,
                'route_point_row_planned_date' => $plannedDate,
                'route_point_row_time_range' => $timeRange,
                'route_point_row_special_conditions' => $specialConditions,
                'route_point_row_summary' => implode("\n", $summaryParts),
            ];
        }

        return $rows;
    }

    private function resolveLegIdForStage(Order $order, string $stage): ?int
    {
        if (! $order->relationLoaded('legs')) {
            return null;
        }

        $normalized = $this->normalizeStageIdentifier($stage);

        foreach ($order->legs as $leg) {
            if ($this->normalizeStageIdentifier((string) $leg->description) === $normalized) {
                return (int) $leg->id;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function legIdsForCarrierContractor(Order $order, int $carrierContractorId): array
    {
        if (! $order->relationLoaded('legs')) {
            return [];
        }

        return $order->legs
            ->filter(fn (OrderLeg $leg): bool => $this->legBelongsToCarrier($leg, $carrierContractorId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function legBelongsToCarrier(OrderLeg $leg, int $carrierContractorId): bool
    {
        $performer = is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : [];

        if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
            foreach ($performer['split_carriers'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                if ((int) ($slot['contractor_id'] ?? 0) === $carrierContractorId) {
                    return true;
                }
            }
        }

        if ($leg->relationLoaded('contractorAssignments')) {
            return $leg->contractorAssignments->contains(
                fn (mixed $assignment): bool => (int) ($assignment->contractor_id ?? 0) === $carrierContractorId,
            );
        }

        $performerContractorId = isset($performer['contractor_id']) && $performer['contractor_id'] !== null
            ? (int) $performer['contractor_id']
            : null;

        if ($performerContractorId === $carrierContractorId) {
            return true;
        }

        if ($leg->relationLoaded('contractorAssignment') && $leg->contractorAssignment !== null) {
            return (int) $leg->contractorAssignment->contractor_id === $carrierContractorId;
        }

        return false;
    }

    private function formatLegStageLabel(string $description): string
    {
        $normalized = $this->normalizeStageIdentifier($description);

        if (preg_match('/^leg_(\d+)$/u', $normalized, $matches) === 1) {
            return 'Плечо '.$matches[1];
        }

        return $description !== '' ? $description : $normalized;
    }

    private function normalizeStageIdentifier(?string $stage): string
    {
        $value = trim((string) $stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }

    private function normalizePrintContext(Order $order, ?OrderPrintFormContext $context): ?OrderPrintFormContext
    {
        if ($context === null) {
            return null;
        }

        if ($context->carrierSlot !== null && $context->carrierSlot > 0) {
            return $context;
        }

        $carrierSlot = PrintFormCargoScopeResolver::resolveCarrierSlot($order, $context);
        if ($carrierSlot === null) {
            return $context;
        }

        return new OrderPrintFormContext(
            legStage: $context->legStage,
            carrierContractorId: $context->carrierContractorId,
            routeLegsAsTableRows: $context->routeLegsAsTableRows,
            printParty: $context->printParty,
            carrierSlot: $carrierSlot,
            documentVerificationCode: $context->documentVerificationCode,
            orderDocumentId: $context->orderDocumentId,
        );
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
        $manual = trim((string) data_get($paymentTermsPayload, 'client.payment_terms_text', ''));
        if ($manual !== '') {
            return $manual;
        }

        if (Schema::hasTable('financial_terms') && $order->relationLoaded('financialTerms')) {
            $fromDb = trim((string) ($order->financialTerms->first()?->client_payment_terms ?? ''));
            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        $schedule = data_get($paymentTermsPayload, 'client.payment_schedule');
        if (is_array($schedule) && $schedule !== []) {
            $currency = $this->resolveCustomerCurrencyCode($order, $paymentTermsPayload);

            return PaymentScheduleSummaryFormatter::format(
                $schedule,
                (float) ($order->customer_rate ?? 0),
                $currency,
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
            $relations[] = 'legs.routePoints';

            if (Schema::hasTable('leg_costs')) {
                $relations[] = 'legs.cost';
            }

            if (Schema::hasTable('leg_contractor_assignments')) {
                $relations[] = 'legs.contractorAssignment';
                $relations[] = 'legs.contractorAssignments';
            }
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        if (Schema::hasTable('contractor_contacts')) {
            $relations[] = 'client.contacts';
            $relations[] = 'carrier.contacts';
        }

        return $order->loadMissing($relations);
    }

    private function ensureContractorContactsLoaded(?Contractor $contractor): void
    {
        if (! $contractor instanceof Contractor || ! Schema::hasTable('contractor_contacts')) {
            return;
        }

        $contractor->loadMissing('contacts');
    }

    /**
     * @return array{
     *     customer_name: ?string,
     *     customer_phone: ?string,
     *     customer_email: ?string,
     *     carrier_name: ?string,
     *     carrier_phone: ?string,
     *     carrier_email: ?string
     * }
     */
    private function partyContactsPayload(Order $order, ?Contractor $customerContractor, mixed $carrierContractor): array
    {
        $customerFallback = ContractorPrimaryContactResolver::resolve($customerContractor);
        $carrierFallback = ContractorPrimaryContactResolver::resolve(
            $carrierContractor instanceof Contractor ? $carrierContractor : null,
        );

        return [
            'customer_name' => $this->firstNonEmptyString(
                $order->customer_contact_name,
                $customerFallback['full_name'],
            ),
            'customer_phone' => $this->firstNonEmptyString(
                $order->customer_contact_phone,
                $customerFallback['phone'],
            ),
            'customer_email' => $this->firstNonEmptyString(
                $order->customer_contact_email,
                $customerFallback['email'],
            ),
            'carrier_name' => $this->firstNonEmptyString(
                $order->carrier_contact_name,
                $carrierFallback['full_name'],
            ),
            'carrier_phone' => $this->firstNonEmptyString(
                $order->carrier_contact_phone,
                $carrierFallback['phone'],
            ),
            'carrier_email' => $this->firstNonEmptyString(
                $order->carrier_contact_email,
                $carrierFallback['email'],
            ),
        ];
    }

    private function firstNonEmptyString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @return array{name: string|null, full_name: string|null, email: string|null, phone: string|null}
     */
    private function managerPayload(?User $manager): array
    {
        $fullName = $manager?->name;

        return [
            'name' => RussianGivenName::fromFullName($fullName),
            'full_name' => $fullName,
            'email' => $manager?->email,
            'phone' => $manager?->phone,
        ];
    }

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
            ...($contractor instanceof Contractor ? $contractor->edoPrintPayload() : [
                'edo_provider' => null,
                'edo_number' => null,
            ]),
            ...$nonResident,
            ...($contractor instanceof Contractor ? $contractor->englishRequisitesPrintPayload() : [
                'has_english_requisites' => 'Нет',
                'name_en' => null,
                'full_name_en' => null,
                'legal_address_en' => null,
                'actual_address_en' => null,
                'postal_address_en' => null,
                'contact_person_en' => null,
                'bank_name_en' => null,
                'signer_name_nominative_en' => null,
                'signer_name_prepositional_en' => null,
                'signer_position_en' => null,
                'signer_authority_basis_en' => null,
            ]),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function driverPayload(int $driverId, ?int $fleetDriverId = null, ?array $portalSubmission = null): array
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

        if (is_array($portalSubmission)) {
            $fullName = trim((string) ($portalSubmission['driver_full_name'] ?? ''));

            if ($fullName !== '') {
                return [
                    'full_name' => $fullName,
                    'phone' => filled($portalSubmission['driver_phone'] ?? null)
                        ? (string) $portalSubmission['driver_phone']
                        : null,
                    'passport_data' => filled($portalSubmission['driver_license'] ?? null)
                        ? (string) $portalSubmission['driver_license']
                        : null,
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
     * @return array{brand: ?string, number: ?string, trailer_brand: ?string, trailer_plate: ?string, cargo_body_type: ?string, trailer_type: ?string}
     */
    private function vehiclePayload(Order $order, array $driver, ?int $fleetVehicleId, Collection $cargoItems, ?array $portalSubmission = null): array
    {
        $cargoTruckBody = $this->resolveCargoDictionaryItemLabels($cargoItems, 'truck_body_type_items', 'truck_body_type_label');

        if ($fleetVehicleId !== null && Schema::hasTable('fleet_vehicles')) {
            /** @var FleetVehicle|null $fleetVehicle */
            $fleetVehicle = FleetVehicle::query()->find($fleetVehicleId);
            if ($fleetVehicle !== null) {
                return [
                    'brand' => $fleetVehicle->tractor_brand,
                    'number' => $fleetVehicle->tractor_plate,
                    'trailer_brand' => $fleetVehicle->trailer_brand,
                    'trailer_plate' => $fleetVehicle->trailer_plate,
                    'cargo_body_type' => $cargoTruckBody,
                    'trailer_type' => $cargoTruckBody,
                ];
            }
        }

        if (is_array($portalSubmission)) {
            $tractorPlate = filled($portalSubmission['tractor_plate'] ?? null)
                ? (string) $portalSubmission['tractor_plate']
                : null;
            $trailerPlate = filled($portalSubmission['trailer_plate'] ?? null)
                ? (string) $portalSubmission['trailer_plate']
                : null;

            if ($tractorPlate !== null || $trailerPlate !== null) {
                return [
                    'brand' => $this->firstFilledValue([
                        $portalSubmission['tractor_brand'] ?? null,
                        $portalSubmission['trailer_brand'] ?? null,
                    ]),
                    'number' => $this->firstFilledValue([$tractorPlate, $trailerPlate]),
                    'trailer_brand' => filled($portalSubmission['trailer_brand'] ?? null)
                        ? (string) $portalSubmission['trailer_brand']
                        : null,
                    'trailer_plate' => $trailerPlate,
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
            'trailer_brand' => $this->firstFilledValue([
                data_get($orderWizardState, 'vehicle.trailer_brand'),
                data_get($orderWizardState, 'transport.trailer_brand'),
                data_get($orderMetadata, 'vehicle.trailer_brand'),
                data_get($orderMetadata, 'trailer_brand'),
            ]),
            'trailer_plate' => $this->firstFilledValue([
                data_get($orderWizardState, 'vehicle.trailer_plate'),
                data_get($orderWizardState, 'transport.trailer_plate'),
                data_get($orderMetadata, 'vehicle.trailer_plate'),
                data_get($orderMetadata, 'trailer_plate'),
                data_get($orderMetadata, 'gosnomer_priz'),
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
            $this->injectSingleOverlayImage(
                $processor,
                is_array($overlays[PrintFormImageOverlayPlaceholders::KEY_SIGNATURE] ?? null)
                    ? $overlays[PrintFormImageOverlayPlaceholders::KEY_SIGNATURE]
                    : [],
                PrintFormImageOverlayPlaceholders::KEY_SIGNATURE,
                $overlays,
            ),
            $this->injectSingleOverlayImage(
                $processor,
                is_array($overlays[PrintFormImageOverlayPlaceholders::KEY_STAMP] ?? null)
                    ? $overlays[PrintFormImageOverlayPlaceholders::KEY_STAMP]
                    : [],
                PrintFormImageOverlayPlaceholders::KEY_STAMP,
                $overlays,
            ),
        )));
    }

    /**
     * @return list<string>
     */
    private function overlayPlaceholderList(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        return PrintFormImageOverlayPlaceholders::allReservedNames($overlays);
    }

    /**
     * @param  array<string, mixed>  $overlay
     * @param  array<string, mixed>  $allOverlays
     * @return list<string>
     */
    private function injectSingleOverlayImage(
        TemplateProcessor $processor,
        array $overlay,
        string $overlayKey,
        array $allOverlays,
    ): array {
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

        $widthMm = (float) ($overlay['width_mm'] ?? 30);
        $heightMm = (float) ($overlay['height_mm'] ?? 30);
        $widthPx = max(20, (int) round($widthMm * 3.78));
        $heightPx = max(20, (int) round($heightMm * 3.78));

        $absolutePath = $resolved['absolute'];
        $imagePayload = [
            'path' => $absolutePath,
            'width' => $widthPx,
            'height' => $heightPx,
            'ratio' => true,
        ];

        $placeholder = PrintFormImageOverlayPlaceholders::placeholderNameForKey($overlayKey, $allOverlays);
        PhpWordTemplateOverlayImageInjector::injectImageForAllMacroStyles($processor, $placeholder, $imagePayload);

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
     * @param  array{package_count: float, weight_value: float|null}|null  $scope
     * @return array{package_count: int, total_weight_kg: float, total_volume_m3: float, per_place_weight_kg: float, per_place_volume_m3: float}
     */
    private function cargoPrintMetrics(mixed $cargo, ?array $scope): array
    {
        if (! is_object($cargo)) {
            return [
                'package_count' => 0,
                'total_weight_kg' => 0.0,
                'total_volume_m3' => 0.0,
                'per_place_weight_kg' => 0.0,
                'per_place_volume_m3' => 0.0,
            ];
        }

        $customerPackages = (int) ($cargo->package_count ?? $cargo->pallet_count ?? 0);
        $customerPackages = $customerPackages > 0 ? $customerPackages : 1;
        $perWeightKg = (float) ($cargo->weight ?? 0);
        $perVolumeM3 = (float) ($cargo->volume ?? 0);

        if ($scope === null) {
            $factor = $this->cargoPackageCountFactor($cargo);

            return [
                'package_count' => $factor,
                'total_weight_kg' => $perWeightKg * $factor,
                'total_volume_m3' => $perVolumeM3 * $factor,
                'per_place_weight_kg' => $perWeightKg,
                'per_place_volume_m3' => $perVolumeM3,
            ];
        }

        $packages = max(0, (int) round((float) ($scope['package_count'] ?? 0)));
        $totalWeightKg = $scope['weight_value'] ?? null;
        if ($totalWeightKg === null || ! is_numeric($totalWeightKg) || (float) $totalWeightKg <= 0.0) {
            $totalWeightKg = $perWeightKg * $packages;
        } else {
            $totalWeightKg = (float) $totalWeightKg;
        }

        return [
            'package_count' => $packages,
            'total_weight_kg' => $totalWeightKg,
            'total_volume_m3' => $perVolumeM3 * $packages,
            'per_place_weight_kg' => $packages > 0 ? $totalWeightKg / $packages : $perWeightKg,
            'per_place_volume_m3' => $perVolumeM3,
        ];
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
     *
     * @param  array{package_count: float, weight_value: float|null}|null  $scope
     */
    private function cargoLineDetailText(mixed $cargo, ?array $scope = null): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $metrics = $this->cargoPrintMetrics($cargo, $scope);
        $name = $this->cargoLineNameOnly($cargo);
        $factor = $metrics['package_count'];
        $perWeightKg = $metrics['per_place_weight_kg'];
        $totalWeightKg = $metrics['total_weight_kg'];

        $lines = [];
        $weightLine = 'Вес: '.$this->formatNumber($totalWeightKg).' кг';
        if ($factor > 1) {
            $weightLine .= ' ('.$this->formatNumber($perWeightKg).' кг × '.$factor.')';
        }
        $lines[] = $weightLine;

        $perVol = $metrics['per_place_volume_m3'];
        $totalVol = $metrics['total_volume_m3'];
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

        $lines[] = 'Мест: '.$factor;

        $body = implode("\n", $lines);

        return $name !== '' ? $name."\n".$body : $body;
    }

    /**
     * @param  array{package_count: float, weight_value: float|null}|null  $scope
     */
    private function cargoLineDetailTextForSummaryLine(mixed $cargo, ?array $scope = null): string
    {
        $block = $this->cargoLineDetailText($cargo, $scope);

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
     * @return list<array<string, string>>
     */
    private function buildCargoTableRowsForTemplate(Collection $cargoItems, Order $order, ?OrderPrintFormContext $context): array
    {
        return $cargoItems
            ->values()
            ->map(function (mixed $cargo, int $index) use ($order, $context): array {
                $scope = PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context);
                $metrics = $this->cargoPrintMetrics($cargo, $scope);
                $dimensions = $this->cargoDimensionsSummaryLine($cargo);

                return [
                    'cargo_row_index' => (string) ($index + 1),
                    'cargo_row_name' => $this->cargoLineNameOnly($cargo),
                    'cargo_row_summary' => $this->cargoLineDetailTextForSummaryLine($cargo, $scope),
                    'cargo_row_text' => $this->cargoLineDetailText($cargo, $scope),
                    'cargo_row_weight' => $this->cargoRowWeightLabel($cargo, $scope),
                    'cargo_row_volume' => $this->cargoRowVolumeLabel($cargo, $scope),
                    'cargo_row_packages' => (string) $metrics['package_count'],
                    'cargo_row_packages_label' => CargoPackagesLabelFormatter::countLabel($metrics['package_count']),
                    'cargo_row_pack_type' => CargoPackagesLabelFormatter::packTypeLabel($cargo),
                    'cargo_row_hs_code' => is_object($cargo) ? trim((string) ($cargo->hs_code ?? '')) : '',
                    'cargo_row_dimensions' => $dimensions ?? '',
                ];
            })
            ->all();
    }

    /**
     * @param  array{package_count: float, weight_value: float|null}|null  $scope
     */
    private function cargoRowWeightLabel(mixed $cargo, ?array $scope = null): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $metrics = $this->cargoPrintMetrics($cargo, $scope);
        $totalKg = $metrics['total_weight_kg'];
        $factor = $metrics['package_count'];

        if ($totalKg <= 0.0) {
            return '';
        }

        $label = $this->formatNumber($totalKg).' кг';
        if ($factor > 1) {
            $label .= ' ('.$this->formatNumber($metrics['per_place_weight_kg']).' × '.$factor.')';
        }

        return $label;
    }

    /**
     * @param  array{package_count: float, weight_value: float|null}|null  $scope
     */
    private function cargoRowVolumeLabel(mixed $cargo, ?array $scope = null): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $metrics = $this->cargoPrintMetrics($cargo, $scope);
        $totalVol = $metrics['total_volume_m3'];
        $factor = $metrics['package_count'];

        if ($totalVol <= 0.0) {
            return '';
        }

        $label = $this->formatVolumeNumber($totalVol).' м³';
        if ($factor > 1) {
            $label .= ' ('.$this->formatVolumeNumber($metrics['per_place_volume_m3']).' × '.$factor.')';
        }

        return $label;
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     * @return array<string, string>
     */
    private function cargoPerLinePlaceholderMap(Collection $cargoItems, Order $order, ?OrderPrintFormContext $context): array
    {
        $out = [];
        $values = $cargoItems->values();
        for ($i = 1; $i <= 10; $i++) {
            $cargo = $values->get($i - 1);
            $scope = $cargo !== null
                ? PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context)
                : null;
            $out['line_'.$i.'_text'] = $cargo !== null ? $this->cargoLineDetailText($cargo, $scope) : '';
            $out['line_'.$i.'_name'] = $cargo !== null ? $this->cargoLineNameOnly($cargo) : '';
            $out['line_'.$i.'_summary'] = $cargo !== null ? $this->cargoLineDetailTextForSummaryLine($cargo, $scope) : '';
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
     * @param  Collection<int, mixed>  $placeholders
     * @param  Collection<int, mixed>  $mapping
     * @param  array<string, mixed>  $snapshot
     */
    private function resolvePlaceholderReplacement(
        string $placeholder,
        Collection $placeholders,
        Collection $mapping,
        PrintFormTemplate $template,
        array $snapshot,
    ): string {
        $mappedPath = $this->resolveMappedPath($placeholder, $mapping, $template);
        $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

        return $this->applyLegacyVehiclePlaceholderEnrichment($placeholder, $placeholders, $snapshot, $replacement);
    }

    /**
     * Старые шаблоны часто содержат только ${gosnomer} / ${marka_avto} без ${gosnomer_priz}.
     *
     * @param  Collection<int, mixed>  $placeholders
     * @param  array<string, mixed>  $snapshot
     */
    private function applyLegacyVehiclePlaceholderEnrichment(
        string $placeholder,
        Collection $placeholders,
        array $snapshot,
        string $replacement,
    ): string {
        $hasTrailerPlatePlaceholder = $placeholders->contains(
            fn (mixed $candidate): bool => is_string($candidate) && $this->normalizedPlaceholderIsTrailerPlate($candidate),
        );
        $hasTrailerBrandPlaceholder = $placeholders->contains(
            fn (mixed $candidate): bool => is_string($candidate) && $this->normalizedPlaceholderIsTrailerBrand($candidate),
        );

        if ($this->normalizedPlaceholderIsTractorPlate($placeholder) && ! $hasTrailerPlatePlaceholder) {
            $trailerPlate = trim($this->stringifyValue(data_get($snapshot, 'vehicle.trailer_plate')));
            if ($trailerPlate !== '' && ! str_contains($replacement, $trailerPlate)) {
                $replacement = trim($replacement) === ''
                    ? $trailerPlate
                    : $replacement.' / '.$trailerPlate;
            }
        }

        if ($placeholder === 'marka_avto' && ! $hasTrailerBrandPlaceholder) {
            $trailerBrand = trim($this->stringifyValue(data_get($snapshot, 'vehicle.trailer_brand')));
            if ($trailerBrand !== '' && ! str_contains($replacement, $trailerBrand)) {
                $replacement = trim($replacement) === ''
                    ? $trailerBrand
                    : $replacement.' / '.$trailerBrand;
            }
        }

        return $replacement;
    }

    private function normalizedPlaceholderIsTractorPlate(string $placeholder): bool
    {
        $normalized = mb_strtolower(trim($placeholder), 'UTF-8');

        return in_array($normalized, ['gosnomer', 'gosnomer_ts'], true);
    }

    private function normalizedPlaceholderIsTrailerPlate(string $placeholder): bool
    {
        $normalized = mb_strtolower(trim($placeholder), 'UTF-8');

        return in_array($normalized, ['gosnomer_priz', 'gosnomer_prizepa', 'gosnomer_pritsepa'], true);
    }

    private function normalizedPlaceholderIsTrailerBrand(string $placeholder): bool
    {
        $normalized = mb_strtolower(trim($placeholder), 'UTF-8');

        return in_array($normalized, ['marka_priz', 'marka_prizepa', 'marka_pritsepa'], true);
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
     * @return array{
     *     client_norms_penalties: array<string, mixed>,
     *     carrier_norms_penalties: array<string, mixed>,
     *     carrier_norms_by_leg: list<array<string, mixed>>,
     * }
     */
    private function financialNormsPenaltiesSnapshot(Order $order, ?OrderPrintFormContext $context = null): array
    {
        $wizard = is_array($order->wizard_state) ? $order->wizard_state : [];
        $ft = is_array($wizard['financial_term'] ?? null) ? $wizard['financial_term'] : [];
        $client = is_array($ft['client_norms_penalties'] ?? null) ? $ft['client_norms_penalties'] : [];
        $carrier = is_array($ft['carrier_norms_by_leg'] ?? null) ? $ft['carrier_norms_by_leg'] : [];
        $carrierRowForContext = CarrierNormsPenaltiesForPrintContext::resolveRow(
            $carrier,
            $this->resolveStageForCarrierNormsPenalties($order, $context),
        );

        return [
            'client_norms_penalties' => $this->normsPenaltiesRowForPrintSnapshot($client),
            'carrier_norms_penalties' => $this->normsPenaltiesRowForPrintSnapshot($carrierRowForContext),
            'carrier_norms_by_leg' => array_values(array_map(
                fn (mixed $row): array => $this->normsPenaltiesRowForPrintSnapshot(is_array($row) ? $row : []),
                $carrier,
            )),
        ];
    }

    private function resolveStageForCarrierNormsPenalties(Order $order, ?OrderPrintFormContext $context): ?string
    {
        if ($context === null) {
            return null;
        }

        if ($context->legStage !== null && $context->legStage !== '') {
            return $this->normalizeStageIdentifier($context->legStage);
        }

        if ($context->carrierContractorId === null || $context->carrierContractorId <= 0 || ! $order->relationLoaded('legs')) {
            return null;
        }

        $leg = $order->legs
            ->sortBy('sequence')
            ->first(fn (OrderLeg $leg): bool => $this->legBelongsToCarrier($leg, $context->carrierContractorId));

        if ($leg === null) {
            return null;
        }

        return $this->normalizeStageIdentifier((string) $leg->description);
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

    /**
     * @param  Collection<int, string>  $placeholders
     */
    private function applyBasicTermsTables(
        TemplateProcessor $processor,
        Order $order,
        PrintFormTemplate $template,
        ?OrderPrintFormContext $context,
        Collection $placeholders,
    ): void {
        foreach ($this->resolveBasicTermsPartiesForTemplate($processor, $template, $placeholders) as $party) {
            $cloner = PrintFormBasicTermsTableCloner::forParty($party);

            if ($cloner === null) {
                continue;
            }

            $cloner->apply(
                $processor,
                $this->basicTermsService->resolveTableRowsForPrintParty($order, $party, $context),
            );
        }
    }

    /**
     * @param  Collection<int, string>  $placeholders
     * @return list<string>
     */
    private function resolveBasicTermsPartiesForTemplate(
        TemplateProcessor $processor,
        PrintFormTemplate $template,
        Collection $placeholders,
    ): array {
        $parties = [];

        foreach ([PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER] as $party) {
            $cloner = PrintFormBasicTermsTableCloner::forParty($party);

            if ($cloner !== null && $cloner->templateHasTermsTable($processor)) {
                $parties[] = $party;
            }
        }

        if ($parties !== []) {
            return $parties;
        }

        foreach (PrintFormBasicTermsTableCloner::partiesFromPlaceholders($placeholders) as $party) {
            if (! in_array($party, $parties, true)) {
                $parties[] = $party;
            }
        }

        if ($parties !== []) {
            return $parties;
        }

        $templateParty = (string) ($template->party ?? '');

        if (in_array($templateParty, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            return [$templateParty];
        }

        return [];
    }
}
