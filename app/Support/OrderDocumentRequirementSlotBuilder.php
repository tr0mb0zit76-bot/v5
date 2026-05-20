<?php

namespace App\Support;

/**
 * Слоты обязательных документов по плечам и контрагентам (закрывающие 1:1 с заявками).
 */
final class OrderDocumentRequirementSlotBuilder
{
    private const REQUEST_TYPES = ['request', 'contract_request'];

    private const CLOSING_TYPES = ['upd', 'invoice_factura', 'act'];

    private const WAYBILL_TYPES = ['waybill', 'cmr', 'etrn'];

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>  $performers
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
    public static function buildRules(array $performers, string $clientRequestMode): array
    {
        $mode = $clientRequestMode === 'split_by_leg' ? 'split_by_leg' : 'single_request';
        $rules = [];

        foreach (self::customerRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('customer', $slot, 'customer_request', 'Заявка заказчика', self::REQUEST_TYPES);
        }

        foreach (self::customerRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('customer', $slot, 'customer_closing', 'Закрывающий документ заказчику', self::CLOSING_TYPES, true);
        }

        foreach (self::carrierRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('carrier', $slot, 'carrier_request', 'Заявка перевозчику', self::REQUEST_TYPES);
        }

        foreach (self::carrierRequestSlots($performers, $mode) as $slot) {
            $rules[] = self::requestRule('carrier', $slot, 'carrier_closing', 'Закрывающий документ перевозчика', self::CLOSING_TYPES, true);
        }

        $rules[] = [
            'key' => 'waybill',
            'label' => 'ТН / ЭТрН / пакет товаросопровождающих',
            'description' => 'Бумажная ТН, CMR, ЭТрН или пакет файлов по маршруту: статус «Отправлен» или «Подписан». Можно прикрепить несколько файлов.',
            'party' => 'internal',
            'accepted_types' => self::WAYBILL_TYPES,
            'slot_kind' => 'waybill',
            'slot_key' => 'waybill',
            'contractor_id' => null,
            'order_leg_stage' => null,
            'counterparty_label' => null,
            'allows_multiple' => true,
        ];

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

        if ($allPerformers === []) {
            return [[
                'slotKey' => 'carrier-empty',
                'orderLegStage' => null,
                'contractorId' => null,
                'contractorName' => null,
                'labelSuffix' => '',
            ]];
        }

        if ($clientRequestMode === 'split_by_leg' && count($allPerformers) > 1) {
            $slots = [];
            foreach ($allPerformers as $performer) {
                $stage = self::normalizeStage((string) ($performer['stage'] ?? 'leg_1'));
                $contractorId = isset($performer['contractor_id']) && (int) $performer['contractor_id'] > 0
                    ? (int) $performer['contractor_id']
                    : null;
                $name = trim((string) ($performer['contractor_name'] ?? ''));
                $suffix = $name !== ''
                    ? ' · '.$name.' · '.self::stageLabel($stage)
                    : ' · '.self::stageLabel($stage);
                $slotKey = $contractorId !== null
                    ? "carrier-{$contractorId}-{$stage}"
                    : "carrier-leg-{$stage}";
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
            $allPerformers,
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
     * @param  array{slotKey: string, orderLegStage: string|null, contractorId: int|null, contractorName: string|null, labelSuffix: string}  $slot
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
        ];
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
