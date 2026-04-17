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
            $mappedPath = $this->resolveMappedPath($placeholder, $mapping);
            $replacement = $this->stringifyValue(data_get($snapshot, $mappedPath));

            $processor->setValue($placeholder, $replacement);
            // Some DOCX templates keep `${ placeholder }` with inner spaces.
            $processor->setValue(' '.$placeholder.' ', $replacement);
        }

        if ($placeholders->isNotEmpty()) {
            $processor->setMacroChars('{{', '}}');

            foreach ($placeholders as $placeholder) {
                $mappedPath = $this->resolveMappedPath($placeholder, $mapping);
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

    public function loadOrderContext(Order $order): Order
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

    private function resolveMappedPath(string $placeholder, Collection $mapping): string
    {
        $explicit = $mapping->get($placeholder);
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $legacy = $this->legacyPlaceholderMappings();
        $normalized = $this->normalizeLegacyPlaceholderKey($placeholder);

        return $legacy[$normalized] ?? $placeholder;
    }

    /**
     * @return array<string, string>
     */
    private function legacyPlaceholderMappings(): array
    {
        return [
            'nomer_zayavki' => 'order.order_number',
            'data_zakaza' => 'order.order_date',
            'data_zagruzki' => 'order.loading_date',
            'data_vygruzki' => 'order.unloading_date',
            'vremya_zagruzki' => 'route.loading_time_from',
            'vremya_vygruzki' => 'route.unloading_time_from',
            'address_zagruzki' => 'route.loading_first_address',
            'address_vygruzki' => 'route.unloading_first_address',
            'gorod_zagruzki' => 'route.loading_first_city',
            'gorod_vygruzki' => 'route.unloading_first_city',
            'gruzootpav' => 'cargo_sender.name',
            'gruzopoluchatel' => 'cargo_recipient.name',
            'kontakt_na_zagruzke' => 'cargo_sender.contact_phone',
            'kontakt_na_vygruzke' => 'cargo_recipient.contact_phone',
            'cargo_summary' => 'cargo.summary',
            'stoimost' => 'order.customer_rate',
            'forma_oplaty' => 'order.customer_payment_form',
            'usloviya_oplaty' => 'order.customer_payment_term',
            'primechanya' => 'order.special_notes',
            'fio_voditel' => 'driver.full_name',
            'tel_voditel' => 'driver.phone',
            'passport_voditel' => 'driver.passport_data',
            'marka_avto' => 'vehicle.brand',
            'gosnomer' => 'vehicle.number',
            'tip_pritsepa' => 'vehicle.transport_type',
            'tip_prizepa' => 'vehicle.transport_type',
            'poln_nazv_zak' => 'customer.full_name',
            'kratk_nazv_zak' => 'customer.name',
            'inn' => 'customer.inn',
            'kpp' => 'customer.kpp',
            'ogrn' => 'customer.ogrn',
            'yur_address' => 'customer.legal_address',
            'pocht_address' => 'customer.actual_address',
            'bank' => 'customer.bank_name',
            'bik' => 'customer.bik',
            'r/s' => 'customer.account_number',
            'k/s' => 'customer.correspondent_account',
            'fio_podpisant' => 'customer.signer_name_nominative',
            'fio_podpisant_rod' => 'customer.signer_name_prepositional',
            'dolzhn_podpisant' => 'customer.signer_position',
        ];
    }

    private function normalizeLegacyPlaceholderKey(string $placeholder): string
    {
        $value = mb_strtolower(trim($placeholder), 'UTF-8');
        $value = str_replace(['’', '`', '´'], '', $value);

        return $value;
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
