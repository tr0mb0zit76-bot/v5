<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementExpenseCategory;
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

    public function test_suggests_operational_match_by_crm_payment_token(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО ТокенПеревоз',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2608-0099',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 44000,
            'remaining_amount' => 44000,
            'planned_date' => '2026-08-12',
            'status' => 'pending',
            'installment_sequence' => 1,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-11',
            'direction' => 'out',
            'amount' => 44000,
            'description' => 'Оплата по заказу АС-2608-0099 CRM:АС-2608-0099:P1',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertSame(98, $suggestion['match_confidence']);
        $this->assertStringContainsString('Токен CRM', (string) $suggestion['match_notes']);
    }

    public function test_suggests_operational_match_by_short_letter_order_number(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Диабаз',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-АГ-02',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 230000,
            'remaining_amount' => 230000,
            'planned_date' => '2026-06-13',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-13',
            'direction' => 'in',
            'amount' => 230000,
            'description' => 'ДИАБАЗ ООО / Оплата по сч№11 от 13.06.2026г (перевозка по заявке №АС-АГ-02 Кудеевский-Горный)',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertGreaterThanOrEqual(90, $suggestion['match_confidence']);
    }

    public function test_digit_order_number_still_preferred_over_short_letter_pattern(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Цифра',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2606-0008',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 72000,
            'remaining_amount' => 72000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-20',
            'direction' => 'in',
            'amount' => 72000,
            'description' => 'Оплата по заявке АС-2606-0008 за транспорт',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
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

    public function test_matches_by_order_invoice_number_when_schedule_invoice_empty(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО СчётНаЗаказе',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0203',
            'customer_id' => $customer->id,
            'invoice_number' => 'СЧ-99001',
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 55000,
            'remaining_amount' => 55000,
            'invoice_number' => null,
            'status' => 'pending',
            'counterparty_id' => $customer->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-14',
            'direction' => 'in',
            'amount' => 55000,
            'description' => 'Оплата по счету СЧ-99001',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertStringContainsString('СЧ-99001', (string) $suggestion['match_notes']);
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

    public function test_partial_payment_with_two_same_rate_orders_stays_unselected(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО АВТОСПЕЦСТРОЙ',
            'full_name' => 'ОБЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ АВТОСПЕЦСТРОЙ',
        ]);

        $firstOrder = Order::query()->create([
            'order_number' => 'Г-2607-0003',
            'carrier_id' => $carrier->id,
        ]);

        $secondOrder = Order::query()->create([
            'order_number' => 'АС-ЗА-21',
            'carrier_id' => $carrier->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $firstOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 95000,
            'remaining_amount' => 95000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $secondOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 95000,
            'remaining_amount' => 95000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-13',
            'direction' => 'out',
            'amount' => 35000,
            'description' => 'АВТОСПЕЦСТРОЙ ООО / Оплата по счету 418 от 13.08.2026',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertNull($suggestion['suggested_order_id']);
        $this->assertNull($suggestion['suggested_payment_schedule_id']);
        $this->assertCount(2, $suggestion['suggested_candidates']);
        $this->assertStringContainsString('Несколько заявок', (string) $suggestion['match_notes']);
    }

    public function test_unique_exact_amount_among_partial_candidates_is_selected(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ИП Кочергин Никита Иванович',
            'full_name' => 'Индивидуальный предприниматель Кочергин Никита Иванович',
        ]);

        $exactOrder = Order::query()->create([
            'order_number' => 'АС-ЗА-25',
            'carrier_id' => $carrier->id,
        ]);

        $partialOrder = Order::query()->create([
            'order_number' => 'Г-2607-0013',
            'carrier_id' => $carrier->id,
        ]);

        $exactSchedule = PaymentSchedule::query()->create([
            'order_id' => $exactOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 15000,
            'remaining_amount' => 15000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $partialOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 20000,
            'remaining_amount' => 20000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-18',
            'direction' => 'out',
            'amount' => 15000,
            'description' => 'Кочергин Никита Иванович / Оплата по счету Н4182 от 30.07.2026 за транспортные услуги',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($exactOrder->id, $suggestion['suggested_order_id']);
        $this->assertSame($exactSchedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertGreaterThanOrEqual(70, (int) $suggestion['match_confidence']);
        $this->assertStringContainsString('точной суммой', (string) $suggestion['match_notes']);
        $this->assertGreaterThanOrEqual(2, count($suggestion['suggested_candidates']));
    }

    public function test_patronymic_token_does_not_match_unrelated_individual(): void
    {
        $kochergin = Contractor::query()->create([
            'name' => 'ИП Кочергин Никита Иванович',
        ]);

        $torgaev = Contractor::query()->create([
            'name' => 'ИП Торгаев Олег Иванович',
        ]);

        $kocherginOrder = Order::query()->create([
            'order_number' => 'АС-ЗА-25',
            'carrier_id' => $kochergin->id,
        ]);

        $torgaevOrder = Order::query()->create([
            'order_number' => '1',
            'carrier_id' => $torgaev->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $kocherginOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 15000,
            'remaining_amount' => 15000,
            'status' => 'pending',
            'counterparty_id' => $kochergin->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $torgaevOrder->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 20000,
            'remaining_amount' => 20000,
            'status' => 'pending',
            'counterparty_id' => $torgaev->id,
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-18',
            'direction' => 'out',
            'amount' => 15000,
            'description' => 'Кочергин Никита Иванович / Оплата по счету Н4182 от 30.07.2026',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame($kocherginOrder->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $candidateOrderNumbers = array_column($suggestion['suggested_candidates'], 'order_number');
        $this->assertNotContains('1', $candidateOrderNumbers);
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

    public function test_bank_commission_category_beats_amount_only_operational_candidates(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'bank_fees'],
            [
                'name' => 'Банковские комиссии и сборы',
                'kind' => 'expense',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 10,
            ],
        );

        $customer = Contractor::query()->create(['name' => 'ООО Случайный']);
        $order = Order::query()->create([
            'order_number' => 'АС-2608-0099',
            'customer_id' => $customer->id,
        ]);
        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 40,
            'remaining_amount' => 40,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-07-31',
            'direction' => 'out',
            'amount' => 40,
            'description' => 'Сбербанк ПАО / Комиссия в другие банки (кредитные организации, Банк России) за ПП/ПТ через ДБО. Без НДС.',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $this->assertSame(80, $suggestion['match_confidence']);
        $this->assertNull($suggestion['suggested_payment_schedule_id']);
        $this->assertNotNull($suggestion['suggested_category_id']);
    }

    public function test_astral_maps_to_aur_overhead_category(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'group_overhead'],
            [
                'name' => 'АУР',
                'kind' => 'expense',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 20,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-07-31',
            'direction' => 'out',
            'amount' => 7600,
            'description' => 'АЙ ТИ СПЕЦИАЛИСТ ООО / Астрал Отчетность, счет 1103 от 23.07.26 НДС не облагается.',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('group_overhead', $category?->code);
    }

    public function test_fns_enp_maps_to_taxes_category(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'group_taxes'],
            [
                'name' => 'Налоги',
                'kind' => 'group',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 50,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-07-31',
            'direction' => 'out',
            'amount' => 34143,
            'description' => 'МИ ФНС России по управлению долгом / ЕНП (СВ 06.2026) ЕНП НДС не облагается.',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('group_taxes', $category?->code);
    }

    public function test_online_cards_maps_to_fuel_gsm_category(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'budget_opex_9'],
            [
                'name' => 'ГСМ',
                'kind' => 'overhead',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => false,
                'sort_order' => 60,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-01',
            'direction' => 'out',
            'amount' => 60000,
            'description' => 'ОНЛАЙН КАРДС ООО / Предоплата за ГСМ за ООО "Логистические решения" ИНН 6382093485, по дог',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('ГСМ', $category?->name);
    }

    public function test_salary_registry_maps_to_payroll_group(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'group_payroll'],
            [
                'name' => 'ФОТ',
                'kind' => 'group',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 30,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-03',
            'direction' => 'out',
            'amount' => 500000,
            'description' => 'Сбербанк ПАО / {VO70060} Заработная плата по реестру №2 от 03.08.2026 в соответствии с Договором 86167131 от 28.05.2026',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('group_payroll', $category?->code);
    }

    public function test_ati_license_maps_to_aur_not_services_other(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'group_overhead'],
            [
                'name' => 'АУР',
                'kind' => 'group',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 40,
            ],
        );
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'services_other'],
            [
                'name' => 'Услуги и лицензии (прочее)',
                'kind' => 'overhead',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 41,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-06',
            'direction' => 'out',
            'amount' => 5000,
            'description' => 'АТИ.СУ ООО / Оплата по счету 22621865 от 06.08.2026 за Лицензию на доступ к базе данных В том числе НДС 22 % - 901.64 рублей.',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('group_overhead', $category?->code);
    }

    public function test_dns_retail_maps_to_office_category(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'budget_opex_1'],
            [
                'name' => 'Офис',
                'kind' => 'overhead',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => false,
                'sort_order' => 45,
            ],
        );

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-01',
            'direction' => 'out',
            'amount' => 74498,
            'description' => 'ФИЛИАЛ СРЕДНЕВОЛЖСКИЙ ООО ДНС РИТЕЙЛ / В том числе НДС 22 % - 13434,07 рублей.',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('Офис', $category?->name);
    }

    public function test_reso_leasing_maps_to_leasing_category(): void
    {
        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'budget_opex_7'],
            [
                'name' => 'Лизинг',
                'kind' => 'overhead',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => false,
                'sort_order' => 55,
            ],
        );

        $carrier = Contractor::query()->create([
            'name' => 'РЕСО-ЛИЗИНГ',
            'full_name' => 'ФИЛИАЛ ОБЩЕСТВА С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ РЕСО-ЛИЗИНГ',
        ]);
        $order = Order::query()->create([
            'order_number' => 'АС-2608-0777',
            'carrier_id' => $carrier->id,
        ]);
        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 134833,
            'remaining_amount' => 134833,
            'paid_amount' => 0,
            'status' => 'pending',
            'planned_date' => '2026-08-05',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-08-05',
            'direction' => 'out',
            'amount' => 134833,
            'description' => 'ФИЛИАЛ ОБЩЕСТВА С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ РЕСО-ЛИЗИНГ / Услуги финансовой аренды (лизинг) по д',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('category', $suggestion['match_type']);
        $category = ManagementExpenseCategory::query()->find($suggestion['suggested_category_id']);
        $this->assertSame('Лизинг', $category?->name);
        $this->assertNull($suggestion['suggested_payment_schedule_id']);
    }

    private function matchingService(): ManagementAccountingMatchingService
    {
        return app(ManagementAccountingMatchingService::class);
    }
}
