<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintFormTemplate;
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
    ) {}

    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function generate(PrintFormTemplate $template, Order $order): array
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

        $processor->setMacroChars('${', '}');

        foreach ($placeholders as $placeholder) {
            $mappedPath = $mapping->get($placeholder, $placeholder);
            $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

            $processor->setValue($placeholder, $replacement);
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
        $downloadName = Str::slug($template->code ?: 'template').'-order-'.$order->id.'-draft.docx';
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
        $driver = $this->driverPayload((int) ($order->driver_id ?? 0));
        $vehicle = $this->vehiclePayload($order, $driver);
        $loadingMethod = $this->resolveLoadingMethod($loadingPoints->first(), $order);

        $cargoNames = $cargoItems
            ->map(fn ($cargo): ?string => $cargo->title ?: $cargo->description)
            ->filter()
            ->implode('; ');

        $cargoTotalWeight = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->weight ?? 0));
        $cargoTotalVolume = $cargoItems->sum(fn ($cargo): float => (float) ($cargo->volume ?? 0));
        $cargoTotalPackages = $cargoItems->sum(fn ($cargo): int => (int) ($cargo->package_count ?? $cargo->pallet_count ?? 0));

        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => $this->formatDate($order->order_date),
                'loading_date' => $this->formatDate($order->loading_date),
                'unloading_date' => $this->formatDate($order->unloading_date),
                'status' => $order->status,
                'customer_rate' => $this->formatMoney($order->customer_rate),
                'carrier_rate' => $this->formatMoney($order->carrier_rate),
                'customer_payment_form' => $order->customer_payment_form,
                'customer_payment_term' => $order->customer_payment_term,
                'carrier_payment_form' => $order->carrier_payment_form,
                'carrier_payment_term' => $order->carrier_payment_term,
                'invoice_number' => $order->invoice_number,
                'waybill_number' => $order->waybill_number,
                'special_notes' => $order->special_notes,
            ],
            'cargo_sender' => [
                'name' => $loadingPoints->first()?->sender_name,
                'address' => $loadingPoints->first()?->address,
                'contact' => $loadingPoints->first()?->sender_contact,
                'phone' => $loadingPoints->first()?->sender_phone,
            ],
            'cargo_recipient' => [
                'name' => $unloadingPoints->last()?->recipient_name,
                'address' => $unloadingPoints->last()?->address,
                'contact' => $unloadingPoints->last()?->recipient_contact,
                'phone' => $unloadingPoints->last()?->recipient_phone,
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
                'loading_addresses' => $loadingPoints->pluck('address')->filter()->implode('; '),
                'loading_cities' => $loadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'loading_first_address' => $loadingPoints->first()?->address,
                'loading_first_city' => data_get($loadingPoints->first()?->normalized_data, 'city'),
                'loading_method' => $loadingMethod,
                'unloading_addresses' => $unloadingPoints->pluck('address')->filter()->implode('; '),
                'unloading_cities' => $unloadingPoints->map(fn ($point): ?string => data_get($point->normalized_data, 'city'))->filter()->implode('; '),
                'unloading_first_address' => $unloadingPoints->first()?->address,
                'unloading_first_city' => data_get($unloadingPoints->first()?->normalized_data, 'city'),
            ],
            'cargo' => [
                'summary' => $cargoItems
                    ->map(fn ($cargo): string => trim(implode(', ', array_filter([
                        $cargo->title,
                        $cargo->weight !== null ? $this->formatNumber($cargo->weight).' кг' : null,
                        $cargo->volume !== null ? $this->formatNumber($cargo->volume).' м3' : null,
                    ]))))
                    ->filter()
                    ->implode('; '),
                'names' => $cargoNames,
                'total_weight' => $this->formatNumber($cargoTotalWeight),
                'total_volume' => $this->formatNumber($cargoTotalVolume),
                'total_packages' => (string) $cargoTotalPackages,
            ],
        ];
    }

    private function loadOrderContext(Order $order): Order
    {
        $relations = ['client', 'carrier', 'ownCompany', 'manager'];

        if (Schema::hasTable('order_legs') && Schema::hasTable('route_points')) {
            $relations[] = 'routePoints';
        }

        if (Schema::hasTable('cargos')) {
            $relations[] = 'cargoItems';
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
    private function driverPayload(int $driverId): array
    {
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
    private function vehiclePayload(Order $order, array $driver): array
    {
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
