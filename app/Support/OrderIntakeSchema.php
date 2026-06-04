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
  "own_company": {"name": string|null, "inn": string|null},
  "carrier": {"name": string|null, "inn": string|null},
  "route": {
    "loading": {"address": string|null, "planned_date": string|null, "contact": string|null, "phone": string|null},
    "unloading": {"address": string|null, "planned_date": string|null, "contact": string|null, "phone": string|null}
  },
  "cargo": {"name": string|null, "description": string|null, "weight_kg": number|null, "volume_m3": number|null, "package_count": number|null},
  "commercial": {
    "customer_rate": number|null,
    "customer_vat_percent": number|null,
    "customer_payment_terms": string|null,
    "carrier_rate": number|null,
    "carrier_payment_terms": string|null,
    "customer_order_number": string|null,
    "order_date": string|null
  },
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
- Суммы в рублях: «100 тысяч» → 100000; «сорок» → 40000.
- Условия оплаты заказчика и перевозчика — в customer_payment_terms / carrier_payment_terms; нормализуй разговорные формулы (см. словарь в сообщении пользователя).
- own_company — «своя/наша компания» (исполнитель перевозки в CRM), не путать с customer (заказчик груза).
TEXT;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  list<array<string, mixed>>  $contractorMatches
     * @param  array{id: int, name: string, inn: string|null, score: float}|null  $ownCompanyMatch
     * @return array{patch: array<string, mixed>, preview: list<array{label: string, value: string|null, confidence: float|null}>}
     */
    public static function toWizardPatch(array $extracted, array $contractorMatches = [], ?array $ownCompanyMatch = null): array
    {
        $customer = is_array($extracted['customer'] ?? null) ? $extracted['customer'] : [];
        $route = is_array($extracted['route'] ?? null) ? $extracted['route'] : [];
        $loading = is_array($route['loading'] ?? null) ? $route['loading'] : [];
        $unloading = is_array($route['unloading'] ?? null) ? $route['unloading'] : [];
        $cargo = is_array($extracted['cargo'] ?? null) ? $extracted['cargo'] : [];
        $commercial = is_array($extracted['commercial'] ?? null) ? $extracted['commercial'] : [];

        $customerMatch = self::firstContractorMatch($contractorMatches, 'customer')
            ?? ($contractorMatches[0] ?? null);
        $carrierMatch = self::firstContractorMatch($contractorMatches, 'carrier');

        $matchedClientId = isset($customerMatch['id']) ? (int) $customerMatch['id'] : null;
        $matchedCarrierId = isset($carrierMatch['id']) ? (int) $carrierMatch['id'] : null;
        $matchedOwnCompanyId = isset($ownCompanyMatch['id']) ? (int) $ownCompanyMatch['id'] : null;

        $patch = array_filter([
            'client_id' => $matchedClientId,
            'own_company_id' => $matchedOwnCompanyId,
            'order_date' => self::normalizeDate($commercial['order_date'] ?? null),
            'order_customer_number' => self::nullableString($commercial['customer_order_number'] ?? null),
            'customer_contact_name' => self::nullableString($customer['contact_name'] ?? null),
            'customer_contact_phone' => self::nullableString($customer['contact_phone'] ?? null),
            'customer_contact_email' => self::nullableString($customer['contact_email'] ?? null),
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

        $financialTerm = [];

        if (isset($commercial['customer_rate']) && $commercial['customer_rate'] !== null && $commercial['customer_rate'] !== '') {
            $financialTerm['client_price'] = round((float) $commercial['customer_rate'], 2);
        }

        $clientPaymentTerms = OrderIntakePhraseNormalizer::normalizePaymentTermsText(
            (string) (self::nullableString($commercial['customer_payment_terms'] ?? null) ?? ''),
        );

        if ($clientPaymentTerms !== '') {
            $financialTerm['client_payment_terms'] = $clientPaymentTerms;
        }

        $carrierRate = $commercial['carrier_rate'] ?? null;

        if ($carrierRate !== null && $carrierRate !== '') {
            $carrierTerms = OrderIntakePhraseNormalizer::normalizePaymentTermsText(
                (string) (self::nullableString($commercial['carrier_payment_terms'] ?? null) ?? ''),
            );
            if ($carrierTerms === '') {
                $carrierTerms = null;
            }
            $costRow = [
                'stage' => 'leg_1',
                'amount' => round((float) $carrierRate, 2),
                'payment_terms' => $carrierTerms,
            ];

            if ($matchedCarrierId !== null) {
                $costRow['contractor_id'] = $matchedCarrierId;
            }

            $financialTerm['contractors_costs'] = [$costRow];
        }

        if ($matchedCarrierId !== null) {
            $patch['carrier_contractor_id'] = $matchedCarrierId;

            if ($carrierMatch !== null && isset($carrierMatch['name'])) {
                $patch['carrier_contractor_name'] = (string) $carrierMatch['name'];
            }
        }

        if ($financialTerm !== []) {
            $patch['financial_term'] = $financialTerm;
        }

        $notesParts = array_filter([
            self::nullableString($extracted['notes'] ?? null),
            $clientPaymentTerms !== null ? 'Оплата заказчика: '.$clientPaymentTerms : null,
            isset($commercial['customer_vat_percent']) && $commercial['customer_vat_percent'] !== null && $commercial['customer_vat_percent'] !== ''
                ? 'НДС заказчика: '.$commercial['customer_vat_percent'].'%'
                : null,
        ]);

        if ($notesParts !== []) {
            $patch['special_notes'] = implode("\n", $notesParts);
        }

        $fieldConfidence = is_array($extracted['field_confidence'] ?? null) ? $extracted['field_confidence'] : [];

        $preview = [
            self::previewRow('Заказчик', self::nullableString($customer['name'] ?? null), self::confidence($fieldConfidence, 'customer.name')),
            self::previewRow('ИНН заказчика', self::nullableString($customer['inn'] ?? null), self::confidence($fieldConfidence, 'customer.inn')),
            self::previewRow('Погрузка', $loadingAddress, self::confidence($fieldConfidence, 'route.loading.address')),
            self::previewRow('Выгрузка', $unloadingAddress, self::confidence($fieldConfidence, 'route.unloading.address')),
            self::previewRow('Груз', $cargoName, self::confidence($fieldConfidence, 'cargo.name')),
            self::previewRow('Ставка заказчика', isset($commercial['customer_rate']) ? (string) $commercial['customer_rate'] : null, self::confidence($fieldConfidence, 'commercial.customer_rate')),
            self::previewRow('Условия оплаты заказчика', self::nullableString($commercial['customer_payment_terms'] ?? null), self::confidence($fieldConfidence, 'commercial.customer_payment_terms')),
            self::previewRow('Ставка перевозчика', isset($commercial['carrier_rate']) ? (string) $commercial['carrier_rate'] : null, self::confidence($fieldConfidence, 'commercial.carrier_rate')),
            self::previewRow('Условия оплаты перевозчика', self::nullableString($commercial['carrier_payment_terms'] ?? null), self::confidence($fieldConfidence, 'commercial.carrier_payment_terms')),
        ];

        if ($customerMatch !== null) {
            $preview[] = self::previewRow(
                'Заказчик в CRM',
                (string) ($customerMatch['name'] ?? ''),
                isset($customerMatch['score']) ? (float) $customerMatch['score'] : null,
            );
        }

        if ($carrierMatch !== null) {
            $preview[] = self::previewRow(
                'Перевозчик в CRM',
                (string) ($carrierMatch['name'] ?? ''),
                isset($carrierMatch['score']) ? (float) $carrierMatch['score'] : null,
            );
        }

        $ownCompany = is_array($extracted['own_company'] ?? null) ? $extracted['own_company'] : [];

        $preview[] = self::previewRow(
            'Своя компания',
            self::nullableString($ownCompany['name'] ?? null),
            self::confidence($fieldConfidence, 'own_company.name'),
        );

        if ($ownCompanyMatch !== null) {
            $preview[] = self::previewRow(
                'Своя компания в CRM',
                (string) ($ownCompanyMatch['name'] ?? ''),
                isset($ownCompanyMatch['score']) ? (float) $ownCompanyMatch['score'] : null,
            );
        }

        return [
            'patch' => self::sanitizeWizardPatch($patch),
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

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return array<string, mixed>|null
     */
    private static function firstContractorMatch(array $matches, string $role): ?array
    {
        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            if (($match['role'] ?? '') === $role && isset($match['id'])) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function sanitizeWizardPatch(array $patch): array
    {
        foreach (['order_date', 'loading_date', 'unloading_date'] as $key) {
            if (! array_key_exists($key, $patch)) {
                continue;
            }

            $patch[$key] = self::normalizeDate($patch[$key]);
        }

        if (isset($patch['route_points']) && is_array($patch['route_points'])) {
            foreach ($patch['route_points'] as $index => $point) {
                if (! is_array($point) || ! array_key_exists('planned_date', $point)) {
                    continue;
                }

                $patch['route_points'][$index]['planned_date'] = self::normalizeDate($point['planned_date']);
            }
        }

        return $patch;
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
