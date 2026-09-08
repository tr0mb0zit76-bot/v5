<?php

declare(strict_types=1);

namespace App\Services\OneC;

/**
 * Детектор «обмен ЭТрН с перевозчиком прошёл» по полям документа 1С.
 */
final class OneCEpdEtrnExchangeDetector
{
    /**
     * @param  array<string, mixed>  $document  OData Document_ЭлектроннаяТранспортнаяНакладная
     */
    public function exchangeWithCarrierCompleted(array $document): bool
    {
        $received = mb_strtoupper(trim((string) ($document['ТекущийПолученныйТитул'] ?? '')));
        if ($this->titleIsCarrierAcceptanceOrLater($received)) {
            return true;
        }

        $acceptanceFile = trim((string) ($document['ТитулПеревозчикаПриемкаИдентификаторФайла'] ?? ''));
        if ($acceptanceFile !== '') {
            return true;
        }

        $step = mb_strtoupper(trim((string) ($document['ТекущийШаг'] ?? '')));
        $stepDone = (bool) ($document['ТекущийШагВыполнен'] ?? false);
        if ($stepDone && in_array($step, ['ПОГРУЗКА', 'РАЗГРУЗКА', 'ЗАВЕРШЕНИЕ', 'ЗАВЕРШЕНО'], true)) {
            return true;
        }

        return false;
    }

    public function titleIsCarrierAcceptanceOrLater(string $title): bool
    {
        $title = mb_strtoupper(trim($title));
        if ($title === '') {
            return false;
        }

        // ЭТрН_Титул2 = приёмка перевозчиком; 3+ — дальше по цепочке.
        if (preg_match('/ЭТРН[_\s-]*ТИТУЛ\s*(\d+)/u', $title, $m) === 1) {
            return (int) $m[1] >= 2;
        }

        return str_contains($title, 'ТИТУЛ2')
            || str_contains($title, 'ТИТУЛ 2')
            || str_contains($title, 'ПРИЕМК');
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{
     *     received_title: ?string,
     *     current_title: ?string,
     *     current_step: ?string,
     *     current_step_done: bool,
     *     waybill_number: ?string,
     *     waybill_date: ?string,
     *     customer_ref: ?string,
     *     carrier_ref: ?string,
     *     consignee_ref: ?string,
     *     shipper_ref: ?string,
     *     carrier_acceptance_file: ?string,
     *     exchange_with_carrier: bool
     * }
     */
    public function metaFromDocument(array $document): array
    {
        $waybillNumber = $this->nullableString($document['ТитулГрузоотправителяТранспортнаяНакладнаяНомер'] ?? null);
        $waybillDate = $this->toDate($document['ТитулГрузоотправителяТранспортнаяНакладнаяДата'] ?? null);

        return [
            'received_title' => $this->nullableString($document['ТекущийПолученныйТитул'] ?? null),
            'current_title' => $this->nullableString($document['ТекущийТитул'] ?? null),
            'current_step' => $this->nullableString($document['ТекущийШаг'] ?? null),
            'current_step_done' => (bool) ($document['ТекущийШагВыполнен'] ?? false),
            'waybill_number' => $waybillNumber,
            'waybill_date' => $waybillDate,
            'customer_ref' => $this->nullableUuid($document['СсылкаТитулГрузоотправителяЗаказчик'] ?? null),
            'carrier_ref' => $this->nullableUuid($document['СсылкаТитулГрузоотправителяПеревозчик'] ?? null),
            'consignee_ref' => $this->nullableUuid($document['СсылкаТитулГрузоотправителяГрузополучатель'] ?? null),
            'shipper_ref' => $this->nullableUuid($document['СсылкаТитулГрузоотправителяГрузоотправитель'] ?? null),
            'carrier_acceptance_file' => $this->nullableString($document['ТитулПеревозчикаПриемкаИдентификаторФайла'] ?? null),
            'exchange_with_carrier' => $this->exchangeWithCarrierCompleted($document),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }

    private function nullableUuid(mixed $value): ?string
    {
        $s = $this->nullableString($value);
        if ($s === null || $s === '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        return $s;
    }

    private function toDate(mixed $value): ?string
    {
        $s = $this->nullableString($value);
        if ($s === null || str_starts_with($s, '0001-01-01')) {
            return null;
        }

        return substr($s, 0, 10);
    }
}
