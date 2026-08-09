<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Маппинг заказа CRM → payload реализации БП (услуги).
 *
 * Контракт полей (MVP):
 * - ВидОперации = Услуги
 * - Контрагент по ИНН+КПП (резолв Ref — на стороне клиента 1С)
 * - Сумма = customer_rate
 * - Допреквизиты CRM_OrderId / CRM_OrderNumber
 * - ТЧ Услуги: одна строка
 */
final class OneCRealizationMapper
{
    /**
     * @return array{
     *     document_type: string,
     *     operation_kind: string,
     *     order_id: int,
     *     order_number: string,
     *     amount: string,
     *     currency: string,
     *     document_date: string,
     *     counterparty: array{inn: string, kpp: string|null, name: string|null},
     *     organization_ref: string|null,
     *     service_line: array{
     *         nomenclature_code: string|null,
     *         nomenclature_ref: string|null,
     *         content: string,
     *         quantity: float,
     *         price: string,
     *         amount: string
     *     },
     *     extra_attributes: list<array{name: string, value: string}>,
     *     odata_stub: array<string, mixed>
     * }
     */
    public function map(Order $order): array
    {
        $order->loadMissing('client');

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

        $amount = $this->normalizeAmount($order->customer_rate);
        if ($amount === null || (float) $amount <= 0) {
            throw ValidationException::withMessages([
                'one_c' => 'Ставка заказчика (customer_rate) должна быть больше нуля.',
            ]);
        }

        $orderNumber = trim((string) ($order->order_number ?? ''));
        if ($orderNumber === '') {
            $orderNumber = 'ID-'.$order->id;
        }

        $attrOrderId = (string) config('one_c.extra_attributes.order_id', 'CRM_OrderId');
        $attrOrderNumber = (string) config('one_c.extra_attributes.order_number', 'CRM_OrderNumber');

        $contentTemplate = (string) config(
            'one_c.service_nomenclature.content_template',
            'Транспортные услуги по заказу {order_number}'
        );
        $content = str_replace(
            ['{order_number}', '{order_id}'],
            [$orderNumber, (string) $order->id],
            $contentTemplate
        );

        $documentDate = $order->unloading_date
            ?? $order->order_date
            ?? now()->toDateString();

        if ($documentDate instanceof \DateTimeInterface) {
            $documentDate = $documentDate->format('Y-m-d');
        }

        $payload = [
            'document_type' => 'РеализацияТоваровУслуг',
            'operation_kind' => 'Услуги',
            'order_id' => (int) $order->id,
            'order_number' => $orderNumber,
            'amount' => $amount,
            'currency' => 'RUB',
            'document_date' => (string) $documentDate,
            'counterparty' => [
                'inn' => $inn,
                'kpp' => $kpp,
                'name' => $client->name !== null ? (string) $client->name : null,
            ],
            'organization_ref' => config('one_c.organization_ref'),
            'service_line' => [
                'nomenclature_code' => config('one_c.service_nomenclature.code'),
                'nomenclature_ref' => config('one_c.service_nomenclature.ref'),
                'content' => $content,
                'quantity' => 1.0,
                'price' => $amount,
                'amount' => $amount,
            ],
            'extra_attributes' => [
                ['name' => $attrOrderId, 'value' => (string) $order->id],
                ['name' => $attrOrderNumber, 'value' => $orderNumber],
            ],
        ];

        $payload['odata_stub'] = $this->toODataStub($payload);

        return $payload;
    }

    /**
     * Черновик тела POST в OData (реальные имена свойств уточняются на живой публикации).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toODataStub(array $payload): array
    {
        $line = $payload['service_line'];

        return [
            'Date' => $payload['document_date'].'T12:00:00',
            'ВидОперации' => $payload['operation_kind'],
            'СуммаДокумента' => (float) $payload['amount'],
            'СуммаВключаетНДС' => true,
            'Комментарий' => 'CRM '.$payload['order_number'],
            'Услуги' => [
                [
                    'LineNumber' => '1',
                    'Содержание' => $line['content'],
                    'Количество' => $line['quantity'],
                    'Цена' => (float) $line['price'],
                    'Сумма' => (float) $line['amount'],
                    'Номенклатура_Key' => $line['nomenclature_ref'],
                ],
            ],
            'ДополнительныеРеквизиты' => array_map(
                static fn (array $row): array => [
                    'Свойство' => $row['name'],
                    'Значение' => $row['value'],
                ],
                $payload['extra_attributes']
            ),
            '_crm_counterparty_match' => $payload['counterparty'],
            '_crm_organization_ref' => $payload['organization_ref'],
        ];
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeAmount(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
