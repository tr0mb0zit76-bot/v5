<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Support\OrderFleetTransportDetailsResolver;
use Illuminate\Validation\ValidationException;

/**
 * Маппинг заказа CRM → payload болванки ЭПД (ЭТрН / экспедиторская расписка).
 * Без ставки заказчика и НДС — только стороны, маршрут, груз, ТС/водитель.
 */
final class OneCEpdStubMapper
{
    public function __construct(
        private readonly OrderFleetTransportDetailsResolver $transportDetails,
        private readonly OneCPublicationCatalog $publications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Order $order, string $documentType): array
    {
        if (! in_array($documentType, OrderOneCDocument::EPD_TYPES, true)) {
            throw ValidationException::withMessages([
                'one_c' => 'Неизвестный тип ЭПД: '.$documentType,
            ]);
        }

        $order->loadMissing([
            'client:id,name,inn,kpp',
            'carrier:id,name,inn,kpp',
            'ownCompany:id,name,inn',
            'routePoints',
            'cargoItems',
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $client = $order->client;
        if ($client === null) {
            throw ValidationException::withMessages([
                'one_c' => 'У заказа не указан заказчик.',
            ]);
        }

        $inn = $this->digits((string) ($client->inn ?? ''));
        if ($inn === '' || (strlen($inn) !== 10 && strlen($inn) !== 12)) {
            throw ValidationException::withMessages([
                'one_c' => 'У заказчика должен быть корректный ИНН (10 или 12 цифр) для сопоставления с 1С.',
            ]);
        }

        $kppRaw = trim((string) ($client->kpp ?? ''));
        $kpp = $kppRaw !== '' ? $this->digits($kppRaw) : null;
        if (strlen($inn) === 10 && ($kpp === null || strlen($kpp) !== 9)) {
            throw ValidationException::withMessages([
                'one_c' => 'Для юрлица нужен КПП (9 цифр) для сопоставления с 1С.',
            ]);
        }

        $orderNumber = trim((string) ($order->order_number ?? ''));
        if ($orderNumber === '') {
            $orderNumber = 'ID-'.$order->id;
        }

        $carrier = $this->resolveCarrier($order);
        $transport = $this->transportDetails->resolveForOrder($order);
        $publication = $this->publications->forOrder($order);
        $organizationRef = $publication['organization_ref'] !== ''
            ? $publication['organization_ref']
            : $this->nullableConfigString('one_c.organization_ref');
        $baseUrl = $publication['base_url'] !== ''
            ? $publication['base_url']
            : $this->nullableConfigString('one_c.base_url');

        $documentDate = $order->loading_date
            ?? $order->order_date
            ?? now()->toDateString();
        if ($documentDate instanceof \DateTimeInterface) {
            $documentDate = $documentDate->format('Y-m-d');
        }

        $routePoints = $order->relationLoaded('routePoints')
            ? $order->routePoints
            : $order->legs->flatMap(fn ($leg) => $leg->routePoints)->values();

        $payload = [
            'document_type' => $documentType,
            'order_id' => (int) $order->id,
            'order_number' => $orderNumber,
            'document_date' => (string) $documentDate,
            'parties' => [
                'customer' => [
                    'id' => $order->customer_id,
                    'inn' => $inn,
                    'kpp' => $kpp,
                    'name' => $client->name !== null ? (string) $client->name : null,
                ],
                'carrier' => $carrier,
            ],
            'counterparty' => [
                'inn' => $inn,
                'kpp' => $kpp,
                'name' => $client->name !== null ? (string) $client->name : null,
            ],
            'route_points' => $routePoints->map(static fn ($point): array => [
                'type' => $point->type,
                'address' => $point->address,
                'planned_date' => optional($point->planned_date)?->toDateString(),
                'planned_time_from' => $point->planned_time_from,
                'planned_time_to' => $point->planned_time_to,
            ])->values()->all(),
            'cargo' => $order->relationLoaded('cargoItems')
                ? $order->cargoItems->map(static fn ($cargo): array => [
                    'title' => $cargo->title,
                    'weight' => $cargo->weight,
                    'volume' => $cargo->volume,
                    'package_count' => $cargo->package_count,
                ])->values()->all()
                : [],
            'driver' => [
                'name' => $transport['driver_name'],
                'fleet_driver_id' => $transport['fleet_driver_id'],
            ],
            'vehicle' => [
                'fleet_vehicle_id' => $transport['fleet_vehicle_id'],
                'tractor_brand' => $transport['tractor_brand'],
                'tractor_plate' => $transport['tractor_plate'],
                'trailer_brand' => $transport['trailer_brand'],
                'trailer_plate' => $transport['trailer_plate'],
            ],
            'organization_ref' => $organizationRef,
            'publication_code' => $publication['code'],
            'base_url' => $baseUrl,
        ];

        $comment = sprintf('CRM %s (id %d)', $orderNumber, $order->id);
        $odataStub = [
            'Date' => $payload['document_date'].'T00:00:00',
            'Posted' => false,
            'DeletionMark' => false,
            'Комментарий' => $comment,
            '_crm_counterparty_match' => $payload['counterparty'],
            '_crm_carrier_match' => $carrier,
            '_crm_organization_ref' => $organizationRef,
        ];
        if (is_string($organizationRef) && $organizationRef !== '') {
            $odataStub['Организация_Key'] = $organizationRef;
        }

        // Document_ЭлектроннаяТранспортнаяНакладная (проверено по $metadata 2026-08-12):
        // Контрагент_Key нет; стороны — через СсылкаТитулГрузоотправителя*.
        if ($documentType === OrderOneCDocument::TYPE_ETRN) {
            $odataStub['ТитулГрузоотправителяТранспортнаяНакладнаяНомер'] = $orderNumber;
            $odataStub['ТитулГрузоотправителяТранспортнаяНакладнаяДата'] = $payload['document_date'].'T00:00:00';
        }

        $payload['odata_stub'] = $odataStub;

        return $payload;
    }

    /**
     * @return array{id: mixed, inn: ?string, kpp: ?string, name: ?string}
     */
    private function resolveCarrier(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];
        foreach ($performers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['contractor_name'] ?? ''));
            $id = isset($row['contractor_id']) && $row['contractor_id'] !== null
                ? (int) $row['contractor_id']
                : null;
            if ($name !== '' || ($id !== null && $id > 0)) {
                return [
                    'id' => $id ?? $order->carrier_id,
                    'inn' => isset($row['contractor_inn']) ? $this->digits((string) $row['contractor_inn']) ?: null : ($order->carrier?->inn ? $this->digits((string) $order->carrier->inn) : null),
                    'kpp' => $order->carrier?->kpp ? $this->digits((string) $order->carrier->kpp) : null,
                    'name' => $name !== '' ? $name : ($order->carrier?->name !== null ? (string) $order->carrier->name : null),
                ];
            }
        }

        $carrier = $order->carrier;

        return [
            'id' => $order->carrier_id,
            'inn' => $carrier?->inn ? $this->digits((string) $carrier->inn) : null,
            'kpp' => $carrier?->kpp ? $this->digits((string) $carrier->kpp) : null,
            'name' => $carrier?->name !== null ? (string) $carrier->name : null,
        ];
    }

    private function nullableConfigString(string $key): ?string
    {
        $value = config($key);
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
