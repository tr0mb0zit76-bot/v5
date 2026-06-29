<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Services\ManagementAccounting\ManagementAccountingMatchingService;
use Tests\TestCase;

class ManagementAccountingMatchingServiceTest extends TestCase
{
    public function test_suggests_operational_match_by_contractor_name_and_exact_amount_for_incoming_payment(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
            'full_name' => 'Общество с ограниченной ответственностью Ромашка',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0007',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'remaining_amount' => 120000,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 120000,
            'description' => 'Поступление от ООО Ромашка за перевозку груза',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertGreaterThanOrEqual(80, $suggestion['match_confidence']);
        $this->assertStringContainsString('Ромашка', (string) $suggestion['match_notes']);
    }

    public function test_suggests_operational_match_for_outgoing_carrier_payment(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ИП Волков',
            'full_name' => 'Индивидуальный предприниматель Волков Петр Сергеевич',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0011',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 85000,
            'remaining_amount' => 85000,
            'planned_date' => '2026-06-12',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'out',
            'amount' => 85000,
            'description' => 'Оплата по договору перевозки ИП Волков',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_does_not_match_when_amount_exceeds_open_schedule(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0008',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'remaining_amount' => 120000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 150000,
            'description' => 'Поступление от ООО Ромашка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertNotSame('operational', $suggestion['match_type']);
        $this->assertNull($suggestion['suggested_order_id']);
    }

    public function test_matches_partial_payment_by_contractor_and_open_remainder(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО ТК Тандем',
            'full_name' => 'Общество с ограниченной ответственностью Транспортная компания Тандем',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0201',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 156000,
            'remaining_amount' => 156000,
            'invoice_number' => 'СЧ-78000',
            'planned_date' => '2026-06-15',
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-14',
            'direction' => 'out',
            'amount' => 78000,
            'description' => 'Оплата по счету ТК Тандем перевозка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertStringContainsString('Тандем', (string) $suggestion['match_notes']);
    }

    public function test_matches_by_invoice_number_in_bank_description(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО ТК Тандем',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0202',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 78000,
            'remaining_amount' => 78000,
            'invoice_number' => 'СЧ-45821',
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-14',
            'direction' => 'out',
            'amount' => 78000,
            'description' => 'Платеж по счету № СЧ-45821 без указания контрагента',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertStringContainsString('СЧ-45821', (string) $suggestion['match_notes']);
    }

    public function test_multiple_contractor_matches_return_candidates_without_auto_selection(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $firstOrder = Order::query()->create([
            'order_number' => 'АС-2506-0101',
            'customer_id' => $customer->id,
        ]);

        $secondOrder = Order::query()->create([
            'order_number' => 'АС-2506-0102',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $firstOrder->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'planned_date' => '2026-06-20',
            'status' => 'pending',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $secondOrder->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'planned_date' => '2026-06-05',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'Оплата от ООО Ромашка без номера заявки',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertNull($suggestion['suggested_order_id']);
        $this->assertNull($suggestion['suggested_payment_schedule_id']);
        $this->assertCount(2, $suggestion['suggested_candidates']);
        $this->assertStringContainsString('Несколько заявок', (string) $suggestion['match_notes']);
    }

    public function test_order_number_match_takes_priority_over_contractor_name(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $orderByNumber = Order::query()->create([
            'order_number' => 'АС-2506-0099',
            'customer_id' => $customer->id,
        ]);

        $orderByNameOnly = Order::query()->create([
            'order_number' => 'АС-2506-0001',
            'customer_id' => $customer->id,
        ]);

        $scheduleByNumber = PaymentSchedule::query()->create([
            'order_id' => $orderByNumber->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $orderByNameOnly->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'Оплата по заявке АС-2506-0099 от ООО Ромашка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame($orderByNumber->id, $suggestion['suggested_order_id']);
        $this->assertSame($scheduleByNumber->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_matches_carrier_from_order_performer_when_carrier_id_is_empty(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО ТК Тандем',
            'full_name' => 'Общество с ограниченной ответственностью Транспортная компания Тандем',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'АС-2506-0301',
            'carrier_id' => null,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 78000,
            'remaining_amount' => 78000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-14',
            'direction' => 'out',
            'amount' => 78000,
            'description' => 'Оплата по счету ТК Тандем перевозка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_search_operational_candidates_by_contractor_name(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО ТК Тандем',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0302',
            'carrier_id' => $carrier->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 78000,
            'remaining_amount' => 78000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-14',
            'direction' => 'out',
            'amount' => 78000,
            'description' => 'Платеж без явного названия',
        ]);

        $candidates = $this->matchingService()->searchOperationalCandidates($line, 'тандем');

        $this->assertCount(1, $candidates);
        $this->assertSame('search', $candidates[0]['match_reason']);
    }

    public function test_suggests_customer_schedule_before_direction_fallback(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО КАМИОН',
            'full_name' => 'Общество с ограниченной ответственностью КАМИОН',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2605-0100',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'ООО КАМИОН оплата по договору перевозки',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertNotEmpty($suggestion['suggested_candidates']);
        $this->assertNotSame('Эвристика по направлению', $suggestion['match_notes']);
    }

    public function test_suggests_customer_schedule_by_short_invoice_number_in_description(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО КАМИОН',
            'full_name' => 'Общество с ограниченной ответственностью КАМИОН',
        ]);

        $order = Order::query()->create([
            'order_number' => '1',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 400000,
            'remaining_amount' => 400000,
            'invoice_number' => '1',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'ООО КАМИОН / Частичная оплата по сч.1 от 05.06.2026г. за транспортные услуги',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_operational_candidates_when_remaining_amount_is_zero_but_schedule_open(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО КАМИОН',
        ]);

        $order = Order::query()->create([
            'order_number' => '1',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 400000,
            'remaining_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'ООО КАМИОН частичная оплата перевозки',
        ]);

        $candidates = $this->matchingService()->operationalCandidatesForLine($line);

        $this->assertCount(1, $candidates);
        $this->assertSame('ООО КАМИОН', $candidates[0]['contractor_label']);
    }

    public function test_incoming_amount_only_candidate_when_contractor_name_missing_in_description(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Дайтона моторс',
            'full_name' => 'Общество с ограниченной ответственностью "Дайтона моторс"',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2606-0002',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'type' => 'final',
            'amount' => 250000,
            'remaining_amount' => 250000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 250000,
            'description' => 'Поступление по договору транспортных услуг',
        ]);

        $candidates = $this->matchingService()->operationalCandidatesForLine($line);

        $this->assertCount(1, $candidates);
        $this->assertSame($order->id, $candidates[0]['order_id']);
        $this->assertSame(250000.0, $candidates[0]['amount_due']);
    }

    public function test_prefers_prepayment_slot_before_final_for_same_order(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО "Дайтона моторс"',
            'full_name' => 'Общество с ограниченной ответственностью "Дайтона моторс"',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2606-0001',
            'customer_id' => $customer->id,
        ]);

        $prepayment = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'type' => 'prepayment',
            'installment_sequence' => 1,
            'amount' => 617231,
            'remaining_amount' => 617231,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'type' => 'final',
            'installment_sequence' => 2,
            'amount' => 617230.5,
            'remaining_amount' => 617230.5,
            'planned_date' => '2026-06-18',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 617231,
            'description' => 'ООО "Дайтона моторс" предоплата по перевозке',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($prepayment->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertSame('Предоплата', $suggestion['suggested_candidates'][0]['slot_label'] ?? null);
    }

    public function test_outgoing_partial_payment_matches_short_carrier_name_in_bank_format(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО "СКЛ"',
            'full_name' => 'ОБЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ "СКЛ"',
        ]);

        $otherCarrier = Contractor::query()->create([
            'name' => 'ООО "СПРАВЕДЛИВОСТЬ АВТО ТРАНС"',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-ЗА-01',
            'carrier_id' => $carrier->id,
        ]);

        $decoyOrder = Order::query()->create([
            'order_number' => 'АС-ОН-84',
            'carrier_id' => $otherCarrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'type' => 'final',
            'amount' => 28000,
            'remaining_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $decoyOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'type' => 'final',
            'amount' => 45000,
            'remaining_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'counterparty_id' => $otherCarrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 20000,
            'description' => 'СКЛ ООО / Предоплата за транспортные услуги по счету № 154 от 02.06.2026 г.',
        ]);

        $candidates = $this->matchingService()->operationalCandidatesForLine($line);

        $this->assertCount(1, $candidates);
        $this->assertSame($schedule->id, $candidates[0]['payment_schedule_id']);
        $this->assertSame('АС-ЗА-01', $candidates[0]['order_number']);
        $this->assertSame('contractor', $candidates[0]['match_reason']);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_search_with_unrelated_keyword_keeps_contractor_match_for_outgoing_payment(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО "СКЛ"',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-ЗА-01',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 28000,
            'remaining_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 20000,
            'description' => 'СКЛ ООО / Предоплата за транспортные услуги по счету № 154 от 02.06.2026 г.',
        ]);

        $candidates = $this->matchingService()->searchOperationalCandidates($line, 'предоплата');

        $this->assertNotEmpty($candidates);
        $this->assertSame($schedule->id, $candidates[0]['payment_schedule_id']);
    }

    private function matchingService(): ManagementAccountingMatchingService
    {
        return app(ManagementAccountingMatchingService::class);
    }
}
