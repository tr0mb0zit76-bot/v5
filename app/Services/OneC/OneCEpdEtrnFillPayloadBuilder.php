<?php

declare(strict_types=1);

namespace App\Services\OneC;

/**
 * Снимок заказа CRM → JSON для POST /hs/crm/epd/etrn/fill.
 */
final class OneCEpdEtrnFillPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $stubPayload  результат OneCEpdStubMapper::map()
     * @return array<string, mixed>
     */
    public function build(string $etrnRef, array $stubPayload, ?string $customerRef = null, ?string $carrierRef = null): array
    {
        $customer = is_array($stubPayload['counterparty'] ?? null) ? $stubPayload['counterparty'] : [];
        $carrier = is_array($stubPayload['parties']['carrier'] ?? null) ? $stubPayload['parties']['carrier'] : [];
        $loading = is_array($stubPayload['route']['loading'] ?? null) ? $stubPayload['route']['loading'] : null;
        $unloading = is_array($stubPayload['route']['unloading'] ?? null) ? $stubPayload['route']['unloading'] : null;

        $parties = [
            'customer' => $this->party($customer, $customerRef),
            'shipper' => $this->party($customer, $customerRef),
            'consignee' => $this->party($customer, $customerRef),
            'carrier' => $this->party($carrier, $carrierRef),
        ];

        $cargo = [];
        foreach (is_array($stubPayload['cargo'] ?? null) ? $stubPayload['cargo'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $cargo[] = [
                'title' => $title,
                'weight_kg' => $this->nullableNumber($row['weight'] ?? $row['weight_kg'] ?? null),
                'volume_m3' => $this->nullableNumber($row['volume'] ?? $row['volume_m3'] ?? null),
                'package_count' => $row['package_count'] ?? null,
            ];
        }

        $driverName = trim((string) ($stubPayload['driver']['name'] ?? $stubPayload['driver']['full_name'] ?? ''));
        $vehicle = is_array($stubPayload['vehicle'] ?? null) ? $stubPayload['vehicle'] : [];

        return [
            'etrn_ref' => $etrnRef,
            'organization_ref' => (string) ($stubPayload['organization_ref'] ?? ''),
            'crm_order' => [
                'id' => (int) ($stubPayload['order_id'] ?? 0),
                'number' => (string) ($stubPayload['order_number'] ?? ''),
                'date' => (string) ($stubPayload['document_date'] ?? ''),
            ],
            'parties' => $parties,
            'route' => [
                'loading' => $this->routePoint($loading),
                'unloading' => $this->routePoint($unloading),
            ],
            'cargo' => $cargo,
            'driver' => [
                'full_name' => $driverName !== '' ? $driverName : null,
                'phone' => null,
                'license_series' => null,
                'license_number' => null,
            ],
            'vehicle' => [
                'tractor_brand' => $this->nullableString($vehicle['tractor_brand'] ?? null),
                'tractor_plate' => $this->nullableString($vehicle['tractor_plate'] ?? null),
                'trailer_brand' => $this->nullableString($vehicle['trailer_brand'] ?? null),
                'trailer_plate' => $this->nullableString($vehicle['trailer_plate'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function party(array $party, ?string $ref): array
    {
        $out = [
            'inn' => $this->nullableString($party['inn'] ?? null),
            'kpp' => $this->nullableString($party['kpp'] ?? null),
            'name' => $this->nullableString($party['name'] ?? null),
            'phone' => $this->nullableString($party['phone'] ?? null),
            'ref' => $ref,
        ];

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $point
     * @return array<string, mixed>|null
     */
    private function routePoint(?array $point): ?array
    {
        if ($point === null) {
            return null;
        }

        $postal = $this->nullableString($point['postal_code'] ?? $point['index'] ?? null);
        $house = $this->nullableString($point['house'] ?? $point['building'] ?? null);

        return [
            'address' => $this->nullableString($point['address'] ?? null),
            'index' => $postal,
            'postal_code' => $postal,
            'city' => $this->nullableString($point['city'] ?? null),
            'region' => $this->nullableString($point['region'] ?? null),
            'street' => $this->nullableString($point['street'] ?? null),
            'building' => $house,
            'house' => $house,
            'flat' => $this->nullableString($point['flat'] ?? null),
            'planned_date' => $this->nullableString($point['planned_date'] ?? null),
            'planned_time_from' => $point['planned_time_from'] ?? null,
            'planned_time_to' => $point['planned_time_to'] ?? null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
