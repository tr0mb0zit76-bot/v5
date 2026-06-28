<?php

namespace App\Support;

/**
 * Слоты обязательных документов по плечам и контрагентам (закрывающие 1:1 с заявками).
 */
final class OrderDocumentRequirementSlotBuilder
{
    private const REQUEST_TYPES = ['request', 'contract_request'];

    private const CLOSING_TYPES = ['upd', 'invoice_factura', 'act'];

    /** @var list<string> */
    private const WAYBILL_TYPES = OrderDocumentTransportTypes::VALUES;

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>  $performers
     * @param  array{customer?: string|null, carriers?: array<int, string|null>}  $paymentContext
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>,
     *     slot_kind: string,
     *     slot_key: string,
     *     contractor_id: int|null,
     *     order_leg_stage: string|null,
     *     counterparty_label: string|null,
     *     allows_multiple?: bool
     * }>
     */
    public static function buildRules(
        array $performers,
        string $clientRequestMode,
        array $additionalCosts = [],
        array $paymentContext = [],
    ): array {
        if (OwnFleetCatalog::isOwnFleetCarrierOnly($performers)) {
            return self::buildOwnFleetCarrierOnlyRules($performers, $clientRequestMode, $paymentContext);
        }

        $mode = $clientRequestMode === 'split_by_leg' ? 'split_by_leg' : 'single_request';
        $rules = [];
        $customerPaymentForm = isset($paymentContext['customer']) ? (string) $paymentContext['customer'] : null;
        /** @var array<int, string|null> $carrierPaymentForms */
        $carrierPaymentForms = is_array($paymentContext['carriers'] ?? null) ? $paymentContext['carriers'] : [];

        foreach (self::customerRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('customer', $slot, 'customer_request', 'Заявка заказчика', self::REQUEST_TYPES);
        }

        foreach (self::customerRequestSlots($performers, $mode) as $slot) {
            if (self::closingRequiredForPaymentForm($customerPaymentForm)) {
                $rules[] = self::requestRule(
                    'customer',
                    $slot,
                    'customer_closing',
                    'Закрывающий документ заказчику',
                    self::CLOSING_TYPES,
                    true,
                );
            }
        }

        foreach (self::carrierRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('carrier', $slot, 'carrier_request', 'Заявка перевозчику', self::REQUEST_TYPES);
        }

        foreach (self::carrierRequestSlots($performers, $mode) as $slot) {
            $contractorId = isset($slot['contractorId']) && (int) $slot['contractorId'] > 0
                ? (int) $slot['contractorId']
                : null;
            $carrierPaymentForm = $contractorId !== null ? ($carrierPaymentForms[$contractorId] ?? null) : null;

            if (self::closingRequiredForPaymentForm($carrierPaymentForm)) {
                $rules[] = self::requestRule(
                    'carrier',
                    $slot,
                    'carrier_closing',
                    'Закрывающий документ перевозчика',
                    self::CLOSING_TYPES,
                    true,
                );
            }
        }

        foreach (self::contractorAdditionalCostSlots($additionalCosts) as $slot) {
            $contractorPaymentForm = isset($slot['paymentForm']) ? (string) $slot['paymentForm'] : null;

            if (self::closingRequiredForPaymentForm($contractorPaymentForm)) {
                $rules[] = self::requestRule(
                    'contractor',
                    $slot,
                    'contractor_closing',
                    'Закрывающий документ подрядчику',
                    self::CLOSING_TYPES,
                    true,
                );
            }
        }

        $rules[] = self::waybillRule($performers, $mode);

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return array<string, mixed>
     */
    private static function waybillRule(array $performers, string $clientRequestMode): array
    {
        $carrierLabel = self::primaryCarrierTransportLabel($performers, $clientRequestMode);

        return [
            'key' => 'waybill',
            'label' => OrderDocumentTransportTypes::UNIFIED_LABEL,
            'description' => 'Бумажная ТН, CMR, ЭТрН, ТСД или пакет файлов по маршруту: статус «Отправлен» или «Подписан». Можно прикрепить несколько файлов.',
            'party' => 'carrier',
            'accepted_types' => self::WAYBILL_TYPES,
            'slot_kind' => 'waybill',
            'slot_key' => 'waybill',
            'contractor_id' => null,
            'order_leg_stage' => null,
            'counterparty_label' => $carrierLabel,
            'allows_multiple' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     */
    private static function primaryCarrierTransportLabel(array $performers, string $clientRequestMode): ?string
    {
        foreach (self::carrierRequestSlots($performers, $clientRequestMode) as $slot) {
            $name = trim((string) ($slot['contractorName'] ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $name = trim((string) ($performer['contractor_name'] ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Собственный парк: заявка заказчику, ТСД/ТН; закрывающие заказчику — по форме оплаты (нал — только заявка).
     * Без заявок и закрывающих перевозчика и подрядчиков.
     *
     * @param  array{customer?: string|null, carriers?: array<int, string|null>}  $paymentContext
     * @return list<array<string, mixed>>
     */
    private static function buildOwnFleetCarrierOnlyRules(
        array $performers,
        string $clientRequestMode,
        array $paymentContext = [],
    ): array {
        $customerSlot = [
            'slotKey' => 'customer-all',
            'orderLegStage' => null,
            'contractorId' => null,
            'contractorName' => null,
            'labelSuffix' => '',
        ];

        $customerPaymentForm = isset($paymentContext['customer']) ? (string) $paymentContext['customer'] : null;

        $rules = [
            self::requestRule('customer', $customerSlot, 'customer_request', 'Заявка заказчика', self::REQUEST_TYPES),
        ];

        if (self::closingRequiredForPaymentForm($customerPaymentForm)) {
            $rules[] = self::requestRule(
                'customer',
                $customerSlot,
                'customer_closing',
                'Закрывающий документ заказчику',
                self::CLOSING_TYPES,
                true,
            );
        }

        $rules[] = self::waybillRule($performers, $clientRequestMode);

        return $rules;
    }

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>  $performers
     * @return list<array{slotKey: string, orderLegStage: string|null, contractorId: int|null, contractorName: string|null, labelSuffix: string}>
     */
    private static function customerRequestSlots(array $performers, string $clientRequestMode): array
    {
        if ($clientRequestMode !== 'split_by_leg' || count($performers) <= 1) {
            return [[
                'slotKey' => 'customer-all',
                'orderLegStage' => null,
                'contractorId' => null,
                'contractorName' => null,
                'labelSuffix' => '',
            ]];
        }

        $slots = [];
        foreach ($performers as $performer) {
            $stage = self::normalizeStage((string) ($performer['stage'] ?? 'leg_1'));
            $slots[] = [
                'slotKey' => 'customer-'.$stage,
                'orderLegStage' => $stage,
                'contractorId' => null,
                'contractorName' => null,
                'labelSuffix' => ' · '.self::stageLabel($stage),
            ];
        }

        return $slots;
    }

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>  $performers
     * @return list<array{slotKey: string, orderLegStage: string|null, contractorId: int|null, contractorName: string|null, labelSuffix: string}>
     */
    private static function carrierRequestSlots(array $performers, string $clientRequestMode): array
    {
        $allPerformers = array_values(array_filter($performers, fn (mixed $row): bool => is_array($row)));
        $expanded = self::filterExternalCarrierExpanded(self::expandCarrierPerformers($allPerformers));

        if ($expanded === []) {
            if ($allPerformers === []) {
                return [[
                    'slotKey' => 'carrier-empty',
                    'orderLegStage' => null,
                    'contractorId' => null,
                    'contractorName' => null,
                    'labelSuffix' => '',
                ]];
            }

            return [];
        }

        $hasSplitOnLeg = collect($expanded)->contains(fn (array $row): bool => ($row['carrier_slot'] ?? null) !== null);
        $multiplePhysicalLegs = count($allPerformers) > 1;

        if (($clientRequestMode === 'split_by_leg' && $multiplePhysicalLegs) || $hasSplitOnLeg) {
            $slots = [];
            foreach ($expanded as $performer) {
                $stage = self::normalizeStage((string) ($performer['stage'] ?? 'leg_1'));
                $carrierSlot = $performer['carrier_slot'] ?? null;
                $contractorId = isset($performer['contractor_id']) && (int) $performer['contractor_id'] > 0
                    ? (int) $performer['contractor_id']
                    : null;
                $name = trim((string) ($performer['contractor_name'] ?? ''));
                $slotLabel = $carrierSlot !== null ? ' · Исполнитель '.$carrierSlot : '';
                $suffix = $name !== ''
                    ? ' · '.$name.$slotLabel.' · '.self::stageLabel($stage)
                    : $slotLabel.' · '.self::stageLabel($stage);
                $slotPart = $carrierSlot !== null ? '-slot'.$carrierSlot : '';
                $slotKey = $contractorId !== null
                    ? "carrier-{$contractorId}-{$stage}{$slotPart}"
                    : "carrier-leg-{$stage}{$slotPart}";
                $slots[] = [
                    'slotKey' => $slotKey,
                    'orderLegStage' => $stage,
                    'contractorId' => $contractorId,
                    'contractorName' => $name !== '' ? $name : null,
                    'labelSuffix' => $suffix,
                ];
            }

            return $slots;
        }

        $legs = array_values(array_filter(
            $expanded,
            fn (array $performer): bool => isset($performer['contractor_id']) && (int) $performer['contractor_id'] > 0,
        ));

        if ($legs === []) {
            return [[
                'slotKey' => 'carrier-empty',
                'orderLegStage' => null,
                'contractorId' => null,
                'contractorName' => null,
                'labelSuffix' => '',
            ]];
        }

        $groups = [];
        foreach ($legs as $performer) {
            $contractorId = (int) $performer['contractor_id'];
            $groups[$contractorId][] = $performer;
        }

        $slots = [];
        foreach ($groups as $contractorId => $groupLegs) {
            $name = trim((string) ($groupLegs[0]['contractor_name'] ?? ''));
            $legTitles = implode(', ', array_map(
                fn (array $p): string => self::stageLabel(self::normalizeStage((string) ($p['stage'] ?? 'leg_1'))),
                $groupLegs,
            ));
            $suffix = $name !== ''
                ? (count($groupLegs) > 1 ? " · {$name} ({$legTitles})" : " · {$name}")
                : (count($groupLegs) > 1 ? " · {$legTitles}" : '');

            $slots[] = [
                'slotKey' => "carrier-{$contractorId}",
                'orderLegStage' => null,
                'contractorId' => $contractorId,
                'contractorName' => $name !== '' ? $name : null,
                'labelSuffix' => $suffix,
            ];
        }

        return $slots;
    }

    /**
     * @param  list<array<string, mixed>>  $additionalCosts
     * @return list<array{slotKey: string, orderLegStage: string|null, contractorId: int|null, contractorName: string|null, labelSuffix: string, paymentForm: string|null}>
     */
    private static function contractorAdditionalCostSlots(array $additionalCosts): array
    {
        $slots = [];

        foreach ($additionalCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $contractorId = isset($row['contractor_id']) && $row['contractor_id'] !== null && $row['contractor_id'] !== ''
                ? (int) $row['contractor_id']
                : null;

            if ($contractorId === null) {
                continue;
            }

            $rowId = trim((string) ($row['id'] ?? ''));
            $slotKey = $rowId !== '' ? "contractor-{$contractorId}-{$rowId}" : "contractor-{$contractorId}";
            $name = trim((string) ($row['contractor_name'] ?? ''));

            $slots[] = [
                'slotKey' => $slotKey,
                'orderLegStage' => null,
                'contractorId' => $contractorId,
                'contractorName' => $name !== '' ? $name : null,
                'labelSuffix' => $name !== '' ? " · {$name}" : '',
                'paymentForm' => PaymentFormDictionary::normalizeForStorage($row['payment_form'] ?? null),
            ];
        }

        return $slots;
    }

    private static function closingRequiredForPaymentForm(?string $paymentForm): bool
    {
        return ! PaymentScheduleCashBasis::isCash($paymentForm);
    }

    /**
     * @param  array{slotKey: string, orderLegStage: string|null, contractorId: int|null, contractorName: string|null, labelSuffix: string, paymentForm?: string|null}  $slot
     * @param  list<string>  $acceptedTypes
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>,
     *     slot_kind: string,
     *     slot_key: string,
     *     contractor_id: int|null,
     *     order_leg_stage: string|null,
     *     counterparty_label: string|null,
     *     allows_multiple?: bool
     * }
     */
    private static function requestRule(
        string $party,
        array $slot,
        string $slotKind,
        string $labelPrefix,
        array $acceptedTypes,
        bool $isClosing = false,
    ): array {
        $description = $isClosing
            ? 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».'
            : 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.';

        return [
            'key' => "{$slotKind}:{$slot['slotKey']}",
            'label' => $labelPrefix.$slot['labelSuffix'],
            'description' => $description,
            'party' => $party,
            'accepted_types' => $acceptedTypes,
            'slot_kind' => $slotKind,
            'slot_key' => $slot['slotKey'],
            'contractor_id' => $slot['contractorId'],
            'order_leg_stage' => $slot['orderLegStage'],
            'counterparty_label' => $slot['contractorName'],
            'allows_multiple' => $isClosing,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{stage: string, carrier_slot: int|null, contractor_id: int|null, contractor_name: string|null}>
     */
    private static function expandCarrierPerformers(array $performers): array
    {
        $expanded = [];

        foreach ($performers as $performer) {
            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $expanded[] = [
                        'stage' => (string) ($performer['stage'] ?? 'leg_1'),
                        'carrier_slot' => isset($slot['slot']) ? (int) $slot['slot'] : null,
                        'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                            ? (int) $slot['contractor_id']
                            : null,
                        'contractor_name' => isset($slot['contractor_name']) ? (string) $slot['contractor_name'] : null,
                        'execution_mode' => isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null,
                    ];
                }

                continue;
            }

            $expanded[] = [
                'stage' => (string) ($performer['stage'] ?? 'leg_1'),
                'carrier_slot' => null,
                'contractor_id' => isset($performer['contractor_id']) && $performer['contractor_id'] !== null
                    ? (int) $performer['contractor_id']
                    : null,
                'contractor_name' => isset($performer['contractor_name']) ? (string) $performer['contractor_name'] : null,
                'execution_mode' => isset($performer['execution_mode']) ? (string) $performer['execution_mode'] : null,
            ];
        }

        return $expanded;
    }

    /**
     * @param  list<array{execution_mode?: string|null}>  $expanded
     * @return list<array{execution_mode?: string|null}>
     */
    private static function filterExternalCarrierExpanded(array $expanded): array
    {
        return array_values(array_filter(
            $expanded,
            fn (array $row): bool => ! OwnFleetCatalog::isOwnFleetExecutionMode($row['execution_mode'] ?? null),
        ));
    }

    private static function normalizeStage(string $stage): string
    {
        if (preg_match('/^Плечо (\d+)$/u', $stage, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^leg_\d+$/', $stage) === 1) {
            return $stage;
        }

        return $stage !== '' ? $stage : 'leg_1';
    }

    private static function stageLabel(string $stage): string
    {
        if (preg_match('/^leg_(\d+)$/', $stage, $matches) === 1) {
            return 'Плечо '.$matches[1];
        }

        return $stage;
    }
}
