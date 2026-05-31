<?php

namespace App\Support;

final class OrderIntakeSchema
{
    public static function llmSystemPrompt(): string
    {
        return <<<'TEXT'
Ты извлекаешь данные из заявки заказчика на перевозку для CRM логистической компании.
Верни ТОЛЬКО один JSON-объект без markdown и комментариев.

Схема:
{
  "customer": {"name": string|null, "inn": string|null, "contact_name": string|null, "contact_phone": string|null, "contact_email": string|null},
  "route": {
    "loading": {"address": string|null, "planned_date": string|null, "contact": string|null, "phone": string|null},
    "unloading": {"address": string|null, "planned_date": string|null, "contact": string|null, "phone": string|null}
  },
  "cargo": {"name": string|null, "description": string|null, "weight_kg": number|null, "volume_m3": number|null, "package_count": number|null},
  "commercial": {"customer_rate": number|null, "customer_order_number": string|null, "order_date": string|null},
  "notes": string|null,
  "confidence": number,
  "field_confidence": object
}

Правила:
- Даты в формате YYYY-MM-DD, если год не указан — текущий год.
- Если поле не найдено — null.
- confidence: 0..1 общая уверенность.
- field_confidence: ключи как в схеме, значения 0..1.
- Не выдумывай адреса и суммы.
TEXT;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  list<array<string, mixed>>  $contractorMatches
     * @return array{patch: array<string, mixed>, preview: list<array{label: string, value: string|null, confidence: float|null}>}
     */
    public static function toWizardPatch(array $extracted, array $contractorMatches = []): array
    {
        $customer = is_array($extracted['customer'] ?? null) ? $extracted['customer'] : [];
        $route = is_array($extracted['route'] ?? null) ? $extracted['route'] : [];
        $loading = is_array($route['loading'] ?? null) ? $route['loading'] : [];
        $unloading = is_array($route['unloading'] ?? null) ? $route['unloading'] : [];
        $cargo = is_array($extracted['cargo'] ?? null) ? $extracted['cargo'] : [];
        $commercial = is_array($extracted['commercial'] ?? null) ? $extracted['commercial'] : [];

        $matchedClientId = null;
        if ($contractorMatches !== [] && isset($contractorMatches[0]['id'])) {
            $matchedClientId = (int) $contractorMatches[0]['id'];
        }

        $patch = array_filter([
            'client_id' => $matchedClientId,
            'order_date' => self::normalizeDate($commercial['order_date'] ?? null),
            'order_customer_number' => self::nullableString($commercial['customer_order_number'] ?? null),
            'customer_contact_name' => self::nullableString($customer['contact_name'] ?? null),
            'customer_contact_phone' => self::nullableString($customer['contact_phone'] ?? null),
            'customer_contact_email' => self::nullableString($customer['contact_email'] ?? null),
            'special_notes' => self::nullableString($extracted['notes'] ?? null),
            'loading_date' => self::normalizeDate($loading['planned_date'] ?? null),
            'unloading_date' => self::normalizeDate($unloading['planned_date'] ?? null),
            'cargo_sender_name' => self::nullableString($loading['contact'] ?? null),
            'cargo_sender_phone' => self::nullableString($loading['phone'] ?? null),
            'cargo_recipient_name' => self::nullableString($unloading['contact'] ?? null),
            'cargo_recipient_phone' => self::nullableString($unloading['phone'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $routePoints = [];
        $loadingAddress = self::nullableString($loading['address'] ?? null);
        if ($loadingAddress !== null) {
            $routePoints[] = array_filter([
                'type' => 'loading',
                'sequence' => 1,
                'stage' => 'leg_1',
                'address' => $loadingAddress,
                'planned_date' => self::normalizeDate($loading['planned_date'] ?? null),
                'contact_person' => self::nullableString($loading['contact'] ?? null),
                'contact_phone' => self::nullableString($loading['phone'] ?? null),
            ]);
        }

        $unloadingAddress = self::nullableString($unloading['address'] ?? null);
        if ($unloadingAddress !== null) {
            $routePoints[] = array_filter([
                'type' => 'unloading',
                'sequence' => 2,
                'stage' => 'leg_1',
                'address' => $unloadingAddress,
                'planned_date' => self::normalizeDate($unloading['planned_date'] ?? null),
                'contact_person' => self::nullableString($unloading['contact'] ?? null),
                'contact_phone' => self::nullableString($unloading['phone'] ?? null),
            ]);
        }

        if ($routePoints !== []) {
            $patch['route_points'] = $routePoints;
        }

        $cargoName = self::nullableString($cargo['name'] ?? null);
        $cargoDescription = self::nullableString($cargo['description'] ?? null);
        if ($cargoName !== null || $cargoDescription !== null || isset($cargo['weight_kg']) || isset($cargo['volume_m3'])) {
            $patch['cargo_items'] = [[
                'name' => $cargoName ?? '',
                'description' => $cargoDescription,
                'weight_kg' => isset($cargo['weight_kg']) ? (float) $cargo['weight_kg'] : null,
                'volume_m3' => isset($cargo['volume_m3']) ? (float) $cargo['volume_m3'] : null,
                'package_count' => isset($cargo['package_count']) ? (int) $cargo['package_count'] : null,
            ]];
        }

        if (isset($commercial['customer_rate']) && $commercial['customer_rate'] !== null && $commercial['customer_rate'] !== '') {
            $patch['financial_term'] = [
                'client_price' => round((float) $commercial['customer_rate'], 2),
            ];
        }

        $fieldConfidence = is_array($extracted['field_confidence'] ?? null) ? $extracted['field_confidence'] : [];

        $preview = [
            self::previewRow('Заказчик', self::nullableString($customer['name'] ?? null), self::confidence($fieldConfidence, 'customer.name')),
            self::previewRow('ИНН заказчика', self::nullableString($customer['inn'] ?? null), self::confidence($fieldConfidence, 'customer.inn')),
            self::previewRow('Погрузка', $loadingAddress, self::confidence($fieldConfidence, 'route.loading.address')),
            self::previewRow('Выгрузка', $unloadingAddress, self::confidence($fieldConfidence, 'route.unloading.address')),
            self::previewRow('Груз', $cargoName, self::confidence($fieldConfidence, 'cargo.name')),
            self::previewRow('Ставка заказчика', isset($commercial['customer_rate']) ? (string) $commercial['customer_rate'] : null, self::confidence($fieldConfidence, 'commercial.customer_rate')),
        ];

        if ($contractorMatches !== []) {
            $preview[] = self::previewRow(
                'Контрагент в CRM',
                (string) ($contractorMatches[0]['name'] ?? ''),
                isset($contractorMatches[0]['score']) ? (float) $contractorMatches[0]['score'] : null,
            );
        }

        return [
            'patch' => $patch,
            'preview' => array_values(array_filter($preview, fn (array $row): bool => filled($row['value']))),
        ];
    }

    /**
     * @param  array<string, mixed>  $fieldConfidence
     */
    private static function confidence(array $fieldConfidence, string $key): ?float
    {
        if (! array_key_exists($key, $fieldConfidence)) {
            return null;
        }

        return is_numeric($fieldConfidence[$key]) ? (float) $fieldConfidence[$key] : null;
    }

    /**
     * @return array{label: string, value: string|null, confidence: float|null}
     */
    private static function previewRow(string $label, ?string $value, ?float $confidence): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'confidence' => $confidence,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        return OrderAgentLexicon::normalizeDateValue($value);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseLlmJson(string $content): array
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('LLM вернул некорректный JSON.');
        }

        return $decoded;
    }
}
