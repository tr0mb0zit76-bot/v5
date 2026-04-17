<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\PrintFormTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class LeadPrintFormDraftService
{
    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
    ) {}

    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function generate(PrintFormTemplate $template, Lead $lead): array
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

        $processor->setMacroChars('${', '}');

        foreach ($placeholders as $placeholder) {
            $mappedPath = $mapping->get($placeholder, $placeholder);
            $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

            $processor->setValue($placeholder, $replacement);
            // Some DOCX templates keep `${ placeholder }` with inner spaces.
            $processor->setValue(' '.$placeholder.' ', $replacement);
        }

        if ($placeholders->isNotEmpty()) {
            $processor->setMacroChars('{{', '}}');

            foreach ($placeholders as $placeholder) {
                $mappedPath = $mapping->get($placeholder, $placeholder);
                $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

                $processor->setValue($placeholder, $replacement);
                $processor->setValue(' '.$placeholder.' ', $replacement);
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

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'download_name' => $downloadName,
        ];
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
}
