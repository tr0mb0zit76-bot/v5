<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\RoutePoint;
use App\Support\OrderClipboardSummaryFormatter;
use App\Support\OrderFleetTransportDetailsResolver;
use App\Support\PaymentAmountVatConverter;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentFormVat;
use App\Support\PaymentMatchToken;
use App\Support\RoutePointNormalizedData;
use Illuminate\Validation\ValidationException;

/**
 * Маппинг заказа CRM → payload реализации БП (услуги, номенклатура ТЭУ).
 *
 * Содержание строки = текст «Сводки по перевозке» (тело без шапки).
 * Ставка НДС / СуммаНДС — из customer_payment_form (CRM → 1С).
 * Допреквизиты CRM_* в типовой ИБ нет — номер заказа и форма оплаты уходят в Комментарий.
 */
final class OneCRealizationMapper
{
    public function __construct(
        private readonly OrderFleetTransportDetailsResolver $transportDetails,
    ) {}

    /**
     * @return array{
     *     document_type: string,
     *     operation_kind: string,
     *     order_id: int,
     *     order_number: string,
     *     amount: string,
     *     currency: string,
     *     document_date: string,
     *     customer_payment_form: ?string,
     *     vat: array{
     *         one_c_rate: string,
     *         rate_percent: float,
     *         amount_includes_vat: bool,
     *         document_without_vat: bool,
     *         vat_amount: float
     *     },
     *     counterparty: array{inn: string, kpp: string|null, name: string|null},
     *     organization_ref: string|null,
     *     currency_ref: string|null,
     *     service_line: array{
     *         nomenclature_code: string|null,
     *         nomenclature_ref: string|null,
     *         content: string,
     *         quantity: float,
     *         price: string,
     *         amount: string,
     *         vat_rate: string,
     *         vat_amount: float
     *     },
     *     extra_attributes: list<array{name: string, value: string}>,
     *     odata_stub: array<string, mixed>
     * }
     */
    public function map(Order $order): array
    {
        $order->loadMissing([
            'client:id,name,inn,kpp',
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

        [$loadingCity, $unloadingCity] = $this->resolveRouteCities($order);
        $transport = $this->transportDetails->resolveForOrder($order);
        $content = OrderClipboardSummaryFormatter::formatServiceContent(
            $orderNumber,
            $order->order_date,
            $loadingCity,
            $unloadingCity,
            $transport['tractor_brand'],
            $transport['tractor_plate'],
            $transport['trailer_brand'],
            $transport['trailer_plate'],
            $transport['driver_name'],
        );
        $content = PaymentMatchToken::forOrderCustomer($order, 1).' '.$content;

        $documentDate = $order->unloading_date
            ?? $order->order_date
            ?? now()->toDateString();

        if ($documentDate instanceof \DateTimeInterface) {
            $documentDate = $documentDate->format('Y-m-d');
        }

        $nomenclatureRef = $this->nullableConfigString('one_c.service_nomenclature.ref');
        $nomenclatureCode = $this->nullableConfigString('one_c.service_nomenclature.code');

        $paymentForm = filled($order->customer_payment_form)
            ? (string) $order->customer_payment_form
            : null;
        $vat = $this->resolveVat((float) $amount, $paymentForm);

        $extraAttributes = [];
        $attrOrderId = $this->nullableConfigString('one_c.extra_attributes.order_id');
        $attrOrderNumber = $this->nullableConfigString('one_c.extra_attributes.order_number');
        if ($attrOrderId !== null) {
            $extraAttributes[] = ['name' => $attrOrderId, 'value' => (string) $order->id];
        }
        if ($attrOrderNumber !== null) {
            $extraAttributes[] = ['name' => $attrOrderNumber, 'value' => $orderNumber];
        }

        $payload = [
            'document_type' => 'РеализацияТоваровУслуг',
            'operation_kind' => 'Услуги',
            'order_id' => (int) $order->id,
            'order_number' => $orderNumber,
            'amount' => $amount,
            'currency' => 'RUB',
            'document_date' => (string) $documentDate,
            'customer_payment_form' => $paymentForm,
            'vat' => $vat,
            'counterparty' => [
                'inn' => $inn,
                'kpp' => $kpp,
                'name' => $client->name !== null ? (string) $client->name : null,
            ],
            'organization_ref' => $this->nullableConfigString('one_c.organization_ref'),
            'currency_ref' => $this->nullableConfigString('one_c.currency_ref'),
            'service_line' => [
                'nomenclature_code' => $nomenclatureCode,
                'nomenclature_ref' => $nomenclatureRef,
                'content' => $content,
                'quantity' => 1.0,
                'price' => $amount,
                'amount' => $amount,
                'vat_rate' => $vat['one_c_rate'],
                'vat_amount' => $vat['vat_amount'],
            ],
            'payment_form_label' => PaymentFormDictionary::labelForCode($paymentForm),
            'extra_attributes' => $extraAttributes,
        ];

        $payload['odata_stub'] = $this->toODataStub($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toODataStub(array $payload): array
    {
        /** @var array{one_c_rate: string, rate_percent: float, amount_includes_vat: bool, document_without_vat: bool, vat_amount: float} $vat */
        $vat = $payload['vat'];
        $line = $payload['service_line'];
        $serviceRow = [
            'LineNumber' => '1',
            'Содержание' => $line['content'],
            'Количество' => $line['quantity'],
            'Цена' => (float) $line['price'],
            'Сумма' => (float) $line['amount'],
            'СтавкаНДС' => $vat['one_c_rate'],
            'СуммаНДС' => $vat['vat_amount'],
        ];
        if (is_string($line['nomenclature_ref'] ?? null) && $line['nomenclature_ref'] !== '') {
            $serviceRow['Номенклатура_Key'] = $line['nomenclature_ref'];
        }

        $comment = sprintf('CRM %s (id %d)', $payload['order_number'], $payload['order_id']);
        $paymentLabel = trim((string) ($payload['payment_form_label'] ?? ''));
        if ($paymentLabel !== '') {
            $comment .= '; оплата: '.$paymentLabel;
        }

        $stub = [
            'Date' => $payload['document_date'].'T12:00:00',
            'ВидОперации' => $payload['operation_kind'],
            'СуммаДокумента' => (float) $payload['amount'],
            'СуммаВключаетНДС' => $vat['amount_includes_vat'],
            'ДокументБезНДС' => $vat['document_without_vat'],
            'Комментарий' => $comment,
            'Услуги' => [$serviceRow],
        ];

        if (is_string($payload['organization_ref'] ?? null) && $payload['organization_ref'] !== '') {
            $stub['Организация_Key'] = $payload['organization_ref'];
        }

        if (is_string($payload['currency_ref'] ?? null) && $payload['currency_ref'] !== '') {
            $stub['ВалютаДокумента_Key'] = $payload['currency_ref'];
        }

        if ($payload['extra_attributes'] !== []) {
            $stub['ДополнительныеРеквизиты'] = array_map(
                static fn (array $row): array => [
                    'Свойство' => $row['name'],
                    'Значение' => $row['value'],
                ],
                $payload['extra_attributes']
            );
        }

        $stub['_crm_counterparty_match'] = $payload['counterparty'];
        $stub['_crm_organization_ref'] = $payload['organization_ref'];

        return $stub;
    }

    /**
     * @return array{
     *     one_c_rate: string,
     *     rate_percent: float,
     *     amount_includes_vat: bool,
     *     document_without_vat: bool,
     *     vat_amount: float
     * }
     */
    private function resolveVat(float $grossAmount, ?string $paymentForm): array
    {
        $rate = PaymentFormVat::ratePercentForCode($paymentForm);

        if ($rate === null && PaymentFormVat::isVatCode($paymentForm)) {
            $rate = PaymentAmountVatConverter::defaultVatRatePercent();
        }

        if ($rate === null || $rate <= 0) {
            return [
                'one_c_rate' => 'БезНДС',
                'rate_percent' => 0.0,
                'amount_includes_vat' => false,
                'document_without_vat' => true,
                'vat_amount' => 0.0,
            ];
        }

        $vatAmount = round($grossAmount * $rate / (100 + $rate), 2);

        return [
            'one_c_rate' => $this->oneCVatRateCode($rate),
            'rate_percent' => $rate,
            'amount_includes_vat' => true,
            'document_without_vat' => false,
            'vat_amount' => $vatAmount,
        ];
    }

    private function oneCVatRateCode(float $ratePercent): string
    {
        $rounded = (int) round($ratePercent);
        if (abs($ratePercent - $rounded) < 0.05) {
            return match ($rounded) {
                22 => 'НДС22',
                20 => 'НДС20',
                18 => 'НДС18',
                10 => 'НДС10',
                7 => 'НДС7',
                5 => 'НДС5',
                0 => 'НДС0',
                default => 'НДС'.$rounded,
            };
        }

        // Дробные ставки в OData этой ИБ — строка; fallback на целое округление.
        return 'НДС'.$rounded;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRouteCities(Order $order): array
    {
        $points = $order->legs
            ->sortBy('sequence')
            ->flatMap(fn ($leg) => $leg->routePoints->sortBy('sequence'))
            ->values();

        $loading = $points->first(fn (RoutePoint $point): bool => $point->type === 'loading');
        $unloading = $points->filter(fn (RoutePoint $point): bool => $point->type === 'unloading')->last();

        return [
            $loading !== null ? $this->routePointCityLabel($loading) : null,
            $unloading !== null ? $this->routePointCityLabel($unloading) : null,
        ];
    }

    private function routePointCityLabel(RoutePoint $point): ?string
    {
        $normalized = RoutePointNormalizedData::resolveForWizard($point);
        $city = trim((string) ($normalized['city'] ?? ''));

        return $city !== '' ? $city : null;
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
