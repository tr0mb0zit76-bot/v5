<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;

class LoadBoardAtiReadinessService
{
    /**
     * @return array{post_id:int, ready:bool, missing:list<array{field:string, label:string}>, warnings:list<array{field:string, label:string}>, payload:array<string, mixed>}
     */
    public function preview(LoadBoardPost $post): array
    {
        $post->loadMissing(['seller:id,name', 'buyer:id,name', 'customer:id,name', 'lead:id,number,title', 'order:id,order_number']);

        $payload = $this->payload($post);
        $missing = $this->missingRequiredFields($post);

        return [
            'post_id' => $post->id,
            'ready' => $missing === [],
            'missing' => $missing,
            'warnings' => $this->recommendedWarnings($post),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LoadBoardPost $post): array
    {
        return array_filter([
            'source' => [
                'system' => 'crm_load_board',
                'postId' => $post->id,
                'leadId' => $post->lead_id,
                'orderId' => $post->order_id,
                'seller' => $post->seller?->only(['id', 'name']),
                'buyer' => $post->buyer?->only(['id', 'name']),
            ],
            'route' => array_filter([
                'loading' => array_filter([
                    'location' => $post->loading_location,
                    'date' => $post->loading_date?->toDateString(),
                ], fn (mixed $value): bool => $this->filledValue($value)),
                'unloading' => array_filter([
                    'location' => $post->unloading_location,
                    'date' => $post->unloading_date?->toDateString(),
                ], fn (mixed $value): bool => $this->filledValue($value)),
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'cargo' => $post->ati_cargo_payload ?: $this->fallbackCargoPayload($post),
            'transportRequirements' => array_filter([
                'text' => $post->transport_type,
                'requirements' => $post->requirements,
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'rate' => array_filter([
                'targetCarrierRate' => $this->nullableFloat($post->target_carrier_rate),
                'customerRate' => $this->nullableFloat($post->customer_rate),
                'currency' => $post->customer_rate_currency ?: 'RUB',
                'paymentForm' => $post->payment_form,
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'customer' => $post->customer?->only(['id', 'name']),
            'comment' => $post->seller_comment,
        ], fn (mixed $value): bool => $this->filledValue($value));
    }

    /**
     * @return list<array{field:string, label:string}>
     */
    private function missingRequiredFields(LoadBoardPost $post): array
    {
        $checks = [
            ['field' => 'loading_location', 'label' => 'Адрес/город погрузки', 'value' => $post->loading_location],
            ['field' => 'unloading_location', 'label' => 'Адрес/город выгрузки', 'value' => $post->unloading_location],
            ['field' => 'loading_date', 'label' => 'Дата погрузки', 'value' => $post->loading_date],
            ['field' => 'cargo_name', 'label' => 'Название груза', 'value' => $post->ati_cargo_name ?: $post->cargo_name],
            ['field' => 'cargo_weight', 'label' => 'Вес груза', 'value' => $post->cargo_weight],
            ['field' => 'cargo_type_id', 'label' => 'Тип груза ATI', 'value' => $post->cargo_type_id],
            ['field' => 'loading_type_items', 'label' => 'Тип погрузки ATI', 'value' => $post->loading_type_items ?: $post->loading_type_id],
            ['field' => 'truck_body_type_items', 'label' => 'Тип кузова ATI', 'value' => $post->truck_body_type_items ?: $post->truck_body_type_id],
        ];

        return collect($checks)
            ->filter(fn (array $check): bool => ! $this->filledValue($check['value']))
            ->map(fn (array $check): array => [
                'field' => $check['field'],
                'label' => $check['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{field:string, label:string}>
     */
    private function recommendedWarnings(LoadBoardPost $post): array
    {
        $checks = [
            ['field' => 'unloading_date', 'label' => 'Дата выгрузки полезна для ATI-публикации', 'value' => $post->unloading_date],
            ['field' => 'cargo_volume', 'label' => 'Объём груза не заполнен', 'value' => $post->cargo_volume],
            ['field' => 'pack_type_id', 'label' => 'Упаковка ATI не выбрана', 'value' => $post->pack_type_id],
            ['field' => 'package_count', 'label' => 'Количество мест не заполнено', 'value' => $post->package_count],
            ['field' => 'target_carrier_rate', 'label' => 'Желаемая ставка перевозчика не указана', 'value' => $post->target_carrier_rate],
            ['field' => 'payment_form', 'label' => 'Форма оплаты не указана', 'value' => $post->payment_form],
        ];

        return collect($checks)
            ->filter(fn (array $check): bool => ! $this->filledValue($check['value']))
            ->map(fn (array $check): array => [
                'field' => $check['field'],
                'label' => $check['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackCargoPayload(LoadBoardPost $post): array
    {
        return array_filter([
            'name' => $post->ati_cargo_name ?: $post->cargo_name,
            'cargoTypeId' => $post->cargo_type_id,
            'cargoType' => $post->cargo_type,
            'cargoTypeName' => $post->cargo_type_label,
            'weight' => $post->cargo_weight === null ? null : [
                'value' => $this->nullableFloat($post->cargo_weight),
                'unit' => 't',
            ],
            'volume' => $this->nullableFloat($post->cargo_volume),
            'packaging' => array_filter([
                'packTypeId' => $post->pack_type_id,
                'packType' => $post->package_type,
                'packTypeName' => $post->pack_type_label,
                'places' => $post->package_count,
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'loading' => array_filter([
                'loadingTypeId' => $post->loading_type_id,
                'loadingType' => $post->loading_type_code,
                'loadingTypeName' => $post->loading_type_label,
                'loadingTypes' => $post->loading_type_items ?? [],
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'transport' => array_filter([
                'truckBodyTypeId' => $post->truck_body_type_id,
                'truckBodyType' => $post->truck_body_type_code,
                'truckBodyTypeName' => $post->truck_body_type_label,
                'truckBodyTypes' => $post->truck_body_type_items ?? [],
                'trailerTypeId' => $post->trailer_type_id,
                'trailerType' => $post->trailer_type_code,
                'trailerTypeName' => $post->trailer_type_label,
                'trailerTypes' => $post->trailer_type_items ?? [],
            ], fn (mixed $value): bool => $this->filledValue($value)),
            'hsCode' => $post->hs_code,
        ], fn (mixed $value): bool => $this->filledValue($value));
    }

    private function filledValue(mixed $value): bool
    {
        return ! ($value === null || $value === '' || $value === []);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
