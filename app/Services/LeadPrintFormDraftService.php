<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\PrintFormTemplate;
use App\Support\PrintFormPlaceholderPathResolver;
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
        $templatePath = Storage::disk($template->file_disk)->path($template->file_path);
        $processor = new TemplateProcessor($templatePath);

        $settings = is_array($template->settings) ? $template->settings : [];
        $placeholders = collect($settings['variables'] ?? [])
            ->merge($this->placeholderExtractor->extractFromDisk($template->file_disk, $template->file_path))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();
        $mapping = collect($settings['variable_mapping'] ?? []);
        $snapshot = $this->buildSnapshot($this->loadLeadContext($lead));
        $overlayPlaceholders = $this->overlayPlaceholderList($template);

        $processor->setMacroChars('${', '}');

        foreach ($placeholders as $placeholder) {
            if (in_array($placeholder, $overlayPlaceholders, true)) {
                continue;
            }

            $mappedPath = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'lead');
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

                $mappedPath = $this->placeholderPathResolver->resolve($placeholder, $mapping->all(), 'lead');
                $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

                $processor->setValue($placeholder, $replacement);
                $processor->setValue(' '.$placeholder.' ', $replacement);
            }
        }

        $overlayStyles = [];
        if ($includeTemplateOverlays) {
            $this->injectTemplateOverlayImages($processor, $template);
            if ($template->shouldApplyCrmOverlayOffsets()) {
                $overlayStyles = $this->buildOverlayFloatingStyles($template);
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

                // См. OrderPrintFormDraftService::applyFloatingImageStyle — привязка к странице для стабильного положения.
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

        $loadingPoints = $routePoints->where('type', 'loading')->values();
        $unloadingPoints = $routePoints->where('type', 'unloading')->values();
        $latestOffer = $lead->relationLoaded('offers') ? $lead->offers->sortByDesc('id')->first() : null;

        $cargoNames = $cargoItems->pluck('name')->filter()->implode('; ');
        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->weight_kg ?? 0));
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->volume_m3 ?? 0));
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
            ],
            'cargo' => [
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => trim(implode(', ', array_filter([
                        $cargo->name,
                        $cargo->weight_kg !== null ? $this->formatNumber($cargo->weight_kg).' кг' : null,
                        $cargo->volume_m3 !== null ? $this->formatNumber($cargo->volume_m3).' м3' : null,
                    ]))))
                    ->filter()
                    ->implode('; '),
                'names' => $cargoNames,
                'total_weight' => $this->formatNumber($cargoTotalWeight),
                'total_volume' => $this->formatNumber($cargoTotalVolume),
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
            'signer_authority_basis' => $contractor?->signer_authority_basis,
        ];
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

    private function injectTemplateOverlayImages(TemplateProcessor $processor, PrintFormTemplate $template): void
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $this->injectSingleOverlayImage($processor, is_array($overlays['internal_signature'] ?? null) ? $overlays['internal_signature'] : [], 'internal_signature_image');
        $this->injectSingleOverlayImage($processor, is_array($overlays['internal_stamp'] ?? null) ? $overlays['internal_stamp'] : [], 'internal_stamp_image');
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
     */
    private function injectSingleOverlayImage(TemplateProcessor $processor, array $overlay, string $defaultPlaceholder): void
    {
        $path = $overlay['path'] ?? null;
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = (string) ($overlay['disk'] ?? 'local');
        if (! Storage::disk($disk)->exists($path)) {
            return;
        }

        $placeholder = trim((string) ($overlay['placeholder'] ?? $defaultPlaceholder));
        if ($placeholder === '') {
            $placeholder = $defaultPlaceholder;
        }

        $widthMm = (float) ($overlay['width_mm'] ?? 30);
        $heightMm = (float) ($overlay['height_mm'] ?? 30);
        $widthPx = max(20, (int) round($widthMm * 3.78));
        $heightPx = max(20, (int) round($heightMm * 3.78));
        $absolutePath = Storage::disk($disk)->path($path);

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
    }
}
