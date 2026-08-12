<?php

declare(strict_types=1);

namespace App\Services\Epd;

use App\Models\Order;

/**
 * HTML-превью экспедиторской расписки (клиент / экспедитор / груз / условия).
 */
class ExpeditionReceiptDraftBuilder
{
    /**
     * @return array{
     *     payload: array<string, mixed>,
     *     missing_required_fields: list<string>,
     *     titles: list<array{code: string, label: string, fields: list<array{key: string, label: string, value: mixed, filled: bool}>}>
     * }
     */
    public function build(Order $order): array
    {
        $order->loadMissing([
            'client:id,name,inn,kpp',
            'ownCompany:id,name,inn',
            'cargoItems',
            'routePoints',
        ]);

        $loading = $order->routePoints->first(fn ($point): bool => $point->type === 'loading');
        $unloading = $order->routePoints->filter(fn ($point): bool => $point->type === 'unloading')->last();

        $payload = [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
            ],
            'parties' => [
                'client' => [
                    'id' => $order->customer_id,
                    'name' => $order->client?->name,
                    'inn' => $order->client?->inn,
                ],
                'forwarder' => [
                    'id' => $order->own_company_id,
                    'name' => $order->ownCompany?->name,
                    'inn' => $order->ownCompany?->inn,
                ],
            ],
            'cargo_items' => $order->cargoItems->map(fn ($cargo): array => [
                'title' => $cargo->title,
                'weight' => $cargo->weight,
                'volume' => $cargo->volume,
                'package_count' => $cargo->package_count,
            ])->values()->all(),
            'acceptance' => [
                'loading_address' => $loading?->address,
                'unloading_address' => $unloading?->address,
                'loading_date' => optional($order->loading_date)?->toDateString(),
            ],
        ];

        $titles = [
            $this->title('client', 'Клиент', [
                ['key' => 'name', 'label' => 'Наименование', 'value' => $order->client?->name],
                ['key' => 'inn', 'label' => 'ИНН', 'value' => $order->client?->inn],
            ]),
            $this->title('forwarder', 'Экспедитор', [
                ['key' => 'name', 'label' => 'Наименование', 'value' => $order->ownCompany?->name],
                ['key' => 'inn', 'label' => 'ИНН', 'value' => $order->ownCompany?->inn],
            ]),
            $this->title('cargo', 'Груз', [
                ['key' => 'titles', 'label' => 'Наименование', 'value' => $order->cargoItems->pluck('title')->filter()->implode(', ') ?: null],
                ['key' => 'weight', 'label' => 'Вес', 'value' => $order->cargoItems->pluck('weight')->filter()->first()],
                ['key' => 'packages', 'label' => 'Мест', 'value' => $order->cargoItems->pluck('package_count')->filter()->first()],
            ]),
            $this->title('acceptance', 'Условия приёма', [
                ['key' => 'loading_address', 'label' => 'Адрес приёма', 'value' => $loading?->address],
                ['key' => 'unloading_address', 'label' => 'Адрес сдачи', 'value' => $unloading?->address],
                ['key' => 'loading_date', 'label' => 'Дата приёма', 'value' => optional($order->loading_date)?->toDateString()],
            ]),
        ];

        return [
            'payload' => $payload,
            'missing_required_fields' => $this->missingFields($payload),
            'titles' => $titles,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, value: mixed}>  $fields
     * @return array{code: string, label: string, fields: list<array{key: string, label: string, value: mixed, filled: bool}>}
     */
    private function title(string $code, string $label, array $fields): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'fields' => array_map(static function (array $field): array {
                $value = $field['value'] ?? null;
                $filled = $value !== null && $value !== '';

                return [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'value' => $value,
                    'filled' => $filled,
                ];
            }, $fields),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function missingFields(array $payload): array
    {
        $required = [
            'order.order_number',
            'parties.client.name',
            'parties.forwarder.name',
        ];

        $missing = [];
        foreach ($required as $path) {
            $value = data_get($payload, $path);
            if ($value === null || $value === '') {
                $missing[] = $path;
            }
        }

        return $missing;
    }
}
