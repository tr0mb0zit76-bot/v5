<?php

namespace App\Support;

final class OrderAgentLexicon
{
    /**
     * @return list<array{key: string, label: string, tool: string, aliases: list<string>}>
     */
    public static function entries(): array
    {
        $inline = [
            'customer_rate' => ['Ставка заказчика', ['ставка клиента', 'цена для заказчика', 'тариф заказчика']],
            'carrier_rate' => ['Ставка перевозчика', ['ставка перевоза', 'тариф перевозчика']],
            'additional_expenses' => ['Доп. расходы', ['дополнительные расходы']],
            'insurance' => ['Страховка', ['страхование']],
            'bonus' => ['Бонус', []],
            'invoice_number' => ['Номер счёта', ['счёт', 'номер счета']],
            'upd_number' => ['Номер УПД', ['упд']],
            'waybill_number' => ['Номер накладной', ['накладная', 'ттн']],
            'track_number_customer' => ['Трек-номер (заказчик)', ['трек заказчика', 'трек клиента']],
            'track_sent_date_customer' => ['Дата отправки трека заказчику', ['отправили трек клиенту']],
            'track_received_date_customer' => ['Дата получения трека заказчиком', []],
            'track_number_carrier' => ['Трек-номер (перевозчик)', ['трек перевозчика']],
            'track_sent_date_carrier' => ['Дата отправки трека перевозчику', []],
            'track_received_date_carrier' => ['Дата получения трека перевозчиком', []],
            'customer_payment_form' => ['Форма оплаты заказчика', []],
            'carrier_payment_form' => ['Форма оплаты перевозчика', []],
            'manual_status' => ['Статус вручную', ['ручной статус', 'статус заказа']],
            'order_date' => ['Дата заявки', ['дата заказа', 'дата оформления']],
        ];

        $entries = [];

        foreach ($inline as $key => [$label, $aliases]) {
            if (! in_array($key, OrderInlineFieldCatalog::ALLOWED_FIELDS, true)) {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'label' => $label,
                'tool' => 'update_order_field',
                'aliases' => $aliases,
            ];
        }

        $entries[] = [
            'key' => 'loading_actual',
            'label' => 'Фактическая дата погрузки',
            'tool' => 'update_order_route_actual',
            'aliases' => [
                'фактическая дата загрузки',
                'фактическая погрузка',
                'факт загрузки',
                'факт погрузки',
                'дата фактической погрузки',
                'дата фактической загрузки',
                'груз забрали',
                'забрали груз',
                'погрузили',
                'фактическая дата loading',
            ],
        ];

        $entries[] = [
            'key' => 'unloading_actual',
            'label' => 'Фактическая дата выгрузки',
            'tool' => 'update_order_route_actual',
            'aliases' => [
                'фактическая выгрузка',
                'факт выгрузки',
                'выгрузили',
                'груз выгрузили',
                'дата фактической выгрузки',
            ],
        ];

        $entries[] = [
            'key' => 'loading_date',
            'label' => 'Плановая дата погрузки',
            'tool' => 'info_only',
            'aliases' => ['плановая погрузка', 'план загрузки', 'дата погрузки план'],
        ];

        $entries[] = [
            'key' => 'unloading_date',
            'label' => 'Плановая дата выгрузки',
            'tool' => 'info_only',
            'aliases' => ['плановая выгрузка', 'план выгрузки'],
        ];

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAgent(): array
    {
        return [
            'fields' => self::entries(),
            'search_orders_hint' => 'В query можно: номер заказа (EXWL-1), id, номер заявки заказчика, название клиента или перевозчика (фрагмент, напр. «Эксвилл»).',
            'notes' => [
                '«Фактическая дата погрузки/загрузки» — это loading_actual (маршрут), не track_sent_date_* и не order_date.',
                '«Груз забрали» обычно означает loading_actual.',
                'Пользователю отвечай русскими названиями полей, не техническими ключами.',
            ],
        ];
    }

    public static function promptHint(): string
    {
        $lines = [
            'Поиск заказа: номер, id, имя клиента/перевозчика (search_orders).',
            'Фактическая погрузка/«груз забрали» → update_order_route_actual kind=loading_actual.',
            'Фактическая выгрузка → update_order_route_actual kind=unloading_actual.',
            'Ставка заказчика → update_order_field field=customer_rate.',
            'Дата заявки → order_date; плановая погрузка на карточке — loading_date (только чтение, не track_*).',
            'При сомнении вызови get_order_field_lexicon.',
        ];

        return implode("\n", $lines);
    }

    public static function labelFor(string $key): ?string
    {
        foreach (self::entries() as $entry) {
            if ($entry['key'] === $key) {
                return $entry['label'];
            }
        }

        return null;
    }

    public static function resolveInlineFieldKey(string $input): ?string
    {
        $resolved = self::resolveEntryKey($input);

        if ($resolved === null || ! in_array($resolved, OrderInlineFieldCatalog::ALLOWED_FIELDS, true)) {
            return null;
        }

        return $resolved;
    }

    public static function resolveRouteActualKind(string $input): ?string
    {
        $resolved = self::resolveEntryKey($input);

        if ($resolved === 'loading_actual' || $resolved === 'unloading_actual') {
            return $resolved;
        }

        return null;
    }

    public static function resolveEntryKey(string $input): ?string
    {
        $needle = self::normalizePhrase($input);

        if ($needle === '') {
            return null;
        }

        foreach (self::entries() as $entry) {
            if (self::normalizePhrase($entry['key']) === $needle) {
                return $entry['key'];
            }

            if (self::normalizePhrase($entry['label']) === $needle) {
                return $entry['key'];
            }

            foreach ($entry['aliases'] as $alias) {
                if (self::normalizePhrase($alias) === $needle) {
                    return $entry['key'];
                }
            }
        }

        foreach (self::entries() as $entry) {
            foreach ([$entry['label'], ...$entry['aliases']] as $candidate) {
                $normalizedCandidate = self::normalizePhrase($candidate);
                if ($normalizedCandidate !== '' && (str_contains($needle, $normalizedCandidate) || str_contains($normalizedCandidate, $needle))) {
                    return $entry['key'];
                }
            }
        }

        return null;
    }

    public static function normalizePhrase(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return str_replace('ё', 'е', $value);
    }

    public static function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        $string = trim(is_string($value) ? $value : (string) $value);

        if ($string === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $string, $matches) === 1) {
            return self::toIsoDateOrNull((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('#^(\d{1,2})[.\-/](\d{1,2})(?:[.\-/](\d{2,4}))?$#u', $string, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = isset($matches[3]) && $matches[3] !== ''
                ? (int) $matches[3]
                : (int) date('Y');

            if ($year < 100) {
                $year += 2000;
            }

            return self::toIsoDateOrNull($year, $month, $day);
        }

        $fallback = PerformerRouteActualDates::normalizeDate($string);

        if ($fallback === null) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fallback, $matches) !== 1) {
            return null;
        }

        return self::toIsoDateOrNull((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    private static function toIsoDateOrNull(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
