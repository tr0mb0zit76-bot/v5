<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\PrintFormTemplate;
use App\Support\CargoPackagesLabelFormatter;
use App\Support\DocxPrintFormPlaceholderPreprocessor;
use App\Support\DocxVmlOverlayStylePatcher;
use App\Support\PhpWordTemplateOverlayImageInjector;
use App\Support\PrintFormCargoTableCloner;
use App\Support\PrintFormImageOverlayPlaceholders;
use App\Support\PrintFormPlaceholderMacroVariants;
use App\Support\PrintFormPlaceholderPathResolver;
use App\Support\PrintFormTemplateDiskSource;
use App\Support\PrintFormTemplateProcessorPreparer;
use App\Support\RussianPositionInflector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class LeadPrintFormDraftService
{
    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
        private readonly PrintFormPlaceholderPathResolver $placeholderPathResolver,
    ) {}

    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function generate(PrintFormTemplate $template, Lead $lead, bool $includeTemplateOverlays = true): array
    {
        $templatePrep = PrintFormTemplateDiskSource::ensureMutableTempCopy(
            PrintFormTemplateDiskSource::prepareLocalPathForPhpWord($template->file_disk, $template->file_path),
        );

        $settings = is_array($template->settings) ? $template->settings : [];
        $placeholders = collect($settings['variables'] ?? [])
            ->merge($this->placeholderExtractor->extractFromDisk($template->file_disk, $template->file_path))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();

        DocxPrintFormPlaceholderPreprocessor::preprocess($templatePrep['path'], $placeholders->all());

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
        $leadForSnapshot = $this->loadLeadContext($lead);
        $snapshot = $this->buildSnapshot($leadForSnapshot);
        $overlayPlaceholders = $this->overlayPlaceholderList($template);
        $cargoItems = $leadForSnapshot->relationLoaded('cargoItems') ? $leadForSnapshot->cargoItems : collect();

        $processor->setMacroChars('${', '}');

        PrintFormTemplateProcessorPreparer::repairTextMacros(
            $processor,
            $placeholders->all(),
        );

        (new PrintFormCargoTableCloner)->apply(
            $processor,
            $this->buildCargoTableRowsForTemplate($cargoItems),
        );

        foreach ($placeholders as $placeholder) {
            if (in_array($placeholder, $overlayPlaceholders, true)) {
                continue;
            }

            if (PrintFormCargoTableCloner::isCargoTablePlaceholder($placeholder)) {
                continue;
            }

            $mappedPath = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'lead');
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

                if (PrintFormCargoTableCloner::isCargoTablePlaceholder($placeholder)) {
                    continue;
                }

                $mappedPath = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'lead');
                $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

                foreach (PrintFormPlaceholderMacroVariants::innerPartsForSetValue($placeholder) as $inner) {
                    $processor->setValue($inner, $replacement);
                }
            }
        }

        $overlayStyles = [];
        if ($includeTemplateOverlays) {
            $this->injectTemplateOverlayImages($processor, $template);
            $overlayStyles = $this->buildOverlayFloatingStyles($template);
            if (! $template->shouldApplyCrmOverlayOffsets()) {
                $overlayStyles = array_map(
                    static fn (): array => ['margin_left_mm' => 0.0, 'margin_top_mm' => 0.0],
                    $overlayStyles,
                );
            }
        }

        $disk = 'local';
        $downloadName = Str::slug($template->code ?: 'template').'-lead-'.$lead->id.'-draft.docx';
        $storagePath = 'generated-documents/drafts/'.$template->id.'/'.Str::uuid().'-'.$downloadName;
        $absoluteTarget = Storage::disk($disk)->path($storagePath);
        $targetDirectory = dirname($absoluteTarget);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create directory: %s', $targetDirectory));
        }

        $processor->saveAs($absoluteTarget);
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
    public function buildLeadSnapshot(Lead $lead): array
    {
        return $this->buildSnapshot($this->loadLeadContext($lead));
    }

    private function loadLeadContext(Lead $lead): Lead
    {
        return $lead->loadMissing([
            'counterparty',
            'responsible',
            'routePoints',
            'cargoItems',
            'offers',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Lead $lead): array
    {
        /** @var Collection<int, mixed> $routePoints */
        $routePoints = $lead->relationLoaded('routePoints') ? $lead->routePoints : collect();
        /** @var Collection<int, mixed> $cargoItems */
        $cargoItems = $lead->relationLoaded('cargoItems') ? $lead->cargoItems : collect();

        $loadingPoints = $routePoints->where('type', 'loading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $unloadingPoints = $routePoints->where('type', 'unloading')->sortBy(fn ($p) => (int) ($p->sequence ?? 0))->values();
        $latestOffer = $lead->relationLoaded('offers') ? $lead->offers->sortByDesc('id')->first() : null;

        $cargoNames = $cargoItems->pluck('name')->filter()->implode('; ');
        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->weight_kg ?? 0) * $this->leadCargoPackageCountFactor($cargo));
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->volume_m3 ?? 0) * $this->leadCargoPackageCountFactor($cargo));
        $cargoTotalPackages = $cargoItems->sum(fn ($cargo): int => (int) ($cargo->package_count ?? 0));

        return [
            'lead' => [
                'id' => $lead->id,
                'number' => $lead->number,
                'status' => $lead->status,
                'source' => $lead->source,
                'title' => $lead->title,
                'description' => $lead->description,
                'transport_type' => $lead->transport_type,
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
                'planned_shipping_date' => $this->formatDate($lead->planned_shipping_date),
                'target_price' => $this->formatMoney($lead->target_price),
                'target_currency' => $lead->target_currency,
                'calculated_cost' => $this->formatMoney($lead->calculated_cost),
                'expected_margin' => $this->formatMoney($lead->expected_margin),
                'next_contact_at' => $this->formatDateTime($lead->next_contact_at),
                'lost_reason' => $lead->lost_reason,
            ],
            'qualification' => [
                'need' => data_get($lead->lead_qualification, 'need'),
                'timeline' => data_get($lead->lead_qualification, 'timeline'),
                'authority' => data_get($lead->lead_qualification, 'authority'),
                'budget' => data_get($lead->lead_qualification, 'budget'),
            ],
            'counterparty' => $this->contractorPayload($lead->counterparty),
            'manager' => [
                'name' => $lead->responsible?->name,
                'email' => $lead->responsible?->email,
                'phone' => $lead->responsible?->phone,
            ],
            'responsible' => [
                'name' => $lead->responsible?->name,
                'email' => $lead->responsible?->email,
                'phone' => $lead->responsible?->phone,
            ],
            'route' => [
                'loading_addresses' => $loadingPoints->pluck('address')->filter()->implode('; '),
                'loading_cities' => $loadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'loading_first_address' => $loadingPoints->first()?->address,
                'loading_first_city' => data_get($loadingPoints->first()?->normalized_data, 'city'),
                'unloading_addresses' => $unloadingPoints->pluck('address')->filter()->implode('; '),
                'unloading_cities' => $unloadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'unloading_first_address' => $unloadingPoints->first()?->address,
                'unloading_first_city' => data_get($unloadingPoints->first()?->normalized_data, 'city'),
                'unloading_last_city' => data_get($unloadingPoints->last()?->normalized_data, 'city'),
                'unloading_last_address' => $unloadingPoints->last()?->address,
            ],
            'cargo' => [
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => $this->leadCargoLineDetailText($cargo))
                    ->filter(fn (string $s): bool => $s !== '')
                    ->implode("\n\n"),
                'names' => $cargoNames,
                'total_weight' => $this->formatNumber($cargoTotalWeight),
                'total_volume' => $this->formatVolumeNumber((float) $cargoTotalVolume),
                'total_packages' => (string) $cargoTotalPackages,
            ],
            'offer' => [
                'number' => $latestOffer?->number,
                'offer_date' => $this->formatDate($latestOffer?->offer_date),
                'price' => $this->formatMoney($latestOffer?->price),
                'currency' => $latestOffer?->currency,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractorPayload(mixed $contractor): array
    {
        $acct = $contractor instanceof Contractor ? $contractor->bankDetailsFromAccountsFallback() : [
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
            'bank_name' => $this->firstNonEmptyString([$contractor?->bank_name, $acct['bank_name']]),
            'bik' => $this->firstNonEmptyString([$contractor?->bik, $acct['bik']]),
            'account_number' => $this->firstNonEmptyString([$contractor?->account_number, $acct['account_number']]),
            'correspondent_account' => $this->firstNonEmptyString([$contractor?->correspondent_account, $acct['correspondent_account']]),
            'signer_name_nominative' => $contractor?->signer_name_nominative,
            'signer_name_prepositional' => $contractor?->signer_name_prepositional,
            'signer_position' => $contractor?->signer_position ?? $contractor?->contact_person_position,
            'signer_position_genitive_auto' => RussianPositionInflector::toGenitive($contractor?->signer_position ?? $contractor?->contact_person_position),
            'signer_authority_basis' => $contractor?->signer_authority_basis,
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
     * @param  list<string|null>  $candidates
     */
    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
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

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y');
        }

        return $value === null ? null : (string) $value;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y H:i');
        }

        return $value === null ? null : (string) $value;
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

    private function formatVolumeNumber(float $value): string
    {
        return number_format($value, 3, ',', ' ');
    }

    /**
     * @param  Collection<int, mixed>  $cargoItems
     * @return list<array<string, string>>
     */
    private function buildCargoTableRowsForTemplate(Collection $cargoItems): array
    {
        return $cargoItems
            ->values()
            ->map(function (mixed $cargo, int $index): array {
                $name = is_object($cargo) ? trim((string) ($cargo->name ?? '')) : '';

                return [
                    'cargo_row_index' => (string) ($index + 1),
                    'cargo_row_name' => $name,
                    'cargo_row_summary' => $this->leadCargoLineSummaryOneLine($cargo),
                    'cargo_row_text' => $this->leadCargoLineDetailText($cargo),
                    'cargo_row_weight' => $this->leadCargoRowWeightLabel($cargo),
                    'cargo_row_volume' => $this->leadCargoRowVolumeLabel($cargo),
                    'cargo_row_packages' => is_object($cargo) ? (string) (int) ($cargo->package_count ?? 0) : '',
                    'cargo_row_packages_label' => is_object($cargo)
                        ? CargoPackagesLabelFormatter::countLabel((int) ($cargo->package_count ?? 0))
                        : '',
                    'cargo_row_pack_type' => CargoPackagesLabelFormatter::packTypeLabel($cargo),
                    'cargo_row_hs_code' => is_object($cargo) ? trim((string) ($cargo->hs_code ?? '')) : '',
                    'cargo_row_dimensions' => '',
                ];
            })
            ->all();
    }

    private function leadCargoLineSummaryOneLine(mixed $cargo): string
    {
        $block = $this->leadCargoLineDetailText($cargo);

        return trim(preg_replace("/\s+/u", ' ', str_replace(["\r\n", "\n", "\r"], ' ', $block)) ?? '');
    }

    private function leadCargoRowWeightLabel(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $factor = $this->leadCargoPackageCountFactor($cargo);
        $totalKg = (float) ($cargo->weight_kg ?? 0) * $factor;

        if ($totalKg <= 0.0) {
            return '';
        }

        $label = $this->formatNumber($totalKg).' кг';
        if ($factor > 1) {
            $label .= ' ('.$this->formatNumber((float) ($cargo->weight_kg ?? 0)).' × '.$factor.')';
        }

        return $label;
    }

    private function leadCargoRowVolumeLabel(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $factor = $this->leadCargoPackageCountFactor($cargo);
        $totalVol = (float) ($cargo->volume_m3 ?? 0) * $factor;

        if ($totalVol <= 0.0) {
            return '';
        }

        $label = $this->formatVolumeNumber($totalVol).' м³';
        if ($factor > 1) {
            $label .= ' ('.$this->formatVolumeNumber((float) ($cargo->volume_m3 ?? 0)).' × '.$factor.')';
        }

        return $label;
    }

    private function leadCargoPackageCountFactor(mixed $cargo): int
    {
        if (! is_object($cargo)) {
            return 1;
        }
        $n = (float) ($cargo->package_count ?? 0);

        return ($n > 0 && is_finite($n)) ? max(1, (int) $n) : 1;
    }

    /**
     * Сводка строки груза лида (вес/объём × число мест), без габаритов — в модели лида их нет.
     */
    private function leadCargoLineDetailText(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        $name = trim((string) ($cargo->name ?? ''));
        $factor = $this->leadCargoPackageCountFactor($cargo);
        $perWeight = (float) ($cargo->weight_kg ?? 0);
        $totalWeight = $perWeight * $factor;

        $lines = [];
        $wLine = 'Вес: '.$this->formatNumber($totalWeight).' кг';
        if ($factor > 1) {
            $wLine .= ' ('.$this->formatNumber($perWeight).' кг × '.$factor.')';
        }
        $lines[] = $wLine;

        $perVol = (float) ($cargo->volume_m3 ?? 0);
        $totalVol = $perVol * $factor;
        if ($totalVol > 0.0) {
            $vLine = 'Объём: '.$this->formatVolumeNumber($totalVol).' м³';
            if ($factor > 1) {
                $vLine .= ' ('.$this->formatVolumeNumber($perVol).' м³ × '.$factor.')';
            }
            $lines[] = $vLine;
        } else {
            $lines[] = 'Объём: —';
        }

        $lines[] = 'Мест: '.(int) ($cargo->package_count ?? 0);

        $body = implode("\n", $lines);

        return $name !== '' ? $name."\n".$body : $body;
    }

    private function injectTemplateOverlayImages(TemplateProcessor $processor, PrintFormTemplate $template): void
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $this->injectSingleOverlayImage(
            $processor,
            is_array($overlays[PrintFormImageOverlayPlaceholders::KEY_SIGNATURE] ?? null)
                ? $overlays[PrintFormImageOverlayPlaceholders::KEY_SIGNATURE]
                : [],
            PrintFormImageOverlayPlaceholders::KEY_SIGNATURE,
            $overlays,
        );
        $this->injectSingleOverlayImage(
            $processor,
            is_array($overlays[PrintFormImageOverlayPlaceholders::KEY_STAMP] ?? null)
                ? $overlays[PrintFormImageOverlayPlaceholders::KEY_STAMP]
                : [],
            PrintFormImageOverlayPlaceholders::KEY_STAMP,
            $overlays,
        );
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
     */
    private function injectSingleOverlayImage(
        TemplateProcessor $processor,
        array $overlay,
        string $overlayKey,
        array $allOverlays,
    ): void {
        $path = $overlay['path'] ?? null;
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = (string) ($overlay['disk'] ?? 'local');
        if (! Storage::disk($disk)->exists($path)) {
            return;
        }

        $widthMm = (float) ($overlay['width_mm'] ?? 30);
        $heightMm = (float) ($overlay['height_mm'] ?? 30);
        $widthPx = max(20, (int) round($widthMm * 3.78));
        $heightPx = max(20, (int) round($heightMm * 3.78));
        $absolutePath = Storage::disk($disk)->path($path);

        $imagePayload = [
            'path' => $absolutePath,
            'width' => $widthPx,
            'height' => $heightPx,
            'ratio' => true,
        ];

        $placeholder = PrintFormImageOverlayPlaceholders::placeholderNameForKey($overlayKey, $allOverlays);
        PhpWordTemplateOverlayImageInjector::injectImageForAllMacroStyles($processor, $placeholder, $imagePayload);
    }
}
