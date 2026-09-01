<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\Finance\ContractorReconciliationService;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorReconciliationServiceTest extends TestCase
{
    public function test_build_accepts_contractor_without_type_and_without_period(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Тест',
            'type' => 'both',
        ]);

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
        );

        $this->assertSame('both', $report['contractor']['type']);
        $this->assertTrue($report['show_as_customer']);
        $this->assertTrue($report['show_as_carrier']);
        $this->assertSame('Услуги для контрагента (он — заказчик)', $report['as_customer']['title']);
        $this->assertSame('Услуги от контрагента (он — перевозчик)', $report['as_carrier']['title']);
    }

    public function test_build_uses_contractor_party_label_for_subcontractor_type(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Подряд',
            'type' => 'contractor',
        ]);

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
        );

        $this->assertSame('contractor', $report['contractor']['type']);
        $this->assertSame('Услуги от контрагента (он — подрядчик)', $report['as_carrier']['title']);
    }

    public function test_build_includes_tranche_payments_for_customer_orders(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0001',
            'order_date' => '2026-06-01',
            'customer_rate' => 150000,
        ]);

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'payment_schedule_id' => $scheduleId,
            'order_id' => $orderId,
            'contractor_id' => $contractor->id,
            'party' => 'customer',
            'amount' => 50000,
            'payment_date' => '2026-06-11',
            'transaction_reference' => 'ПП-100',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('orders', 'upd_number')) {
            DB::table('orders')->where('id', $orderId)->update(['upd_number' => 'УПД-1']);
        }

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
        );

        $row = $report['as_customer']['rows'][0];
        $this->assertCount(1, $row['tranches']);
        $this->assertSame($scheduleId, $row['tranches'][0]['id']);
        $this->assertSame(50000.0, $row['tranches'][0]['paid']);
        $this->assertCount(1, $row['tranches'][0]['payments']);
        $this->assertSame('2026-06-11', $row['tranches'][0]['payments'][0]['date']);
        $this->assertSame(['2026-06-11'], $row['payment_dates']);
        $this->assertSame('2026-06-11', $row['last_payment_date']);
        $this->assertSame('receivable', $row['balance_status']);
        $this->assertSame('Долг', $row['balance_label']);
    }

    public function test_overpayment_is_flagged_when_paid_exceeds_accrued(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Аванс',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0002',
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
        ]);

        if (Schema::hasColumn('orders', 'upd_number')) {
            DB::table('orders')->where('id', $orderId)->update(['upd_number' => 'УПД-2']);
        }

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'planned_date' => '2026-06-10',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'payment_schedule_id' => $scheduleId,
            'order_id' => $orderId,
            'contractor_id' => $contractor->id,
            'party' => 'customer',
            'amount' => 150000,
            'payment_date' => '2026-06-12',
            'transaction_reference' => 'ПП-200',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService))
            ->build($contractor->id, null, null, null);

        $row = $report['as_customer']['rows'][0];
        $this->assertSame('overpayment', $row['balance_status']);
        $this->assertSame(-50000.0, $row['balance']);
        $this->assertSame('overpayment', $report['as_customer']['totals']['balance_status']);
        $this->assertSame(50000.0, $report['as_customer']['totals']['overpayment']);
        $this->assertSame('2026-06-12', $row['last_payment_date']);
        $this->assertSame('Переплата', $row['balance_label']);
    }

    public function test_payment_without_upd_is_settled_when_schedule_fully_paid(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Без УПД',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0003',
            'order_date' => '2026-06-01',
            'customer_rate' => 417000,
        ]);

        if (Schema::hasColumn('orders', 'upd_number')) {
            DB::table('orders')->where('id', $orderId)->update(['upd_number' => null]);
        }

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 417000,
            'paid_amount' => 417000,
            'remaining_amount' => 0,
            'planned_date' => '2026-06-10',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'payment_schedule_id' => $scheduleId,
            'order_id' => $orderId,
            'contractor_id' => $contractor->id,
            'party' => 'customer',
            'amount' => 417000,
            'payment_date' => '2026-06-12',
            'transaction_reference' => 'ПП-417',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService))
            ->build($contractor->id, null, null, null);

        $row = $report['as_customer']['rows'][0];
        $this->assertFalse($row['has_upd']);
        $this->assertSame(417000.0, $row['accrued']);
        $this->assertSame(417000.0, $row['paid']);
        $this->assertSame(0.0, $row['balance']);
        $this->assertSame('settled', $row['balance_status']);
        $this->assertNull($row['balance_label']);
        $this->assertSame('settled', $report['as_customer']['totals']['balance_status']);
    }

    public function test_payment_without_upd_and_without_schedule_counts_as_overpayment_advance(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Аванс без графика',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0004',
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
        ]);

        if (Schema::hasColumn('orders', 'upd_number')) {
            DB::table('orders')->where('id', $orderId)->update(['upd_number' => null]);
        }

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'paid_amount' => 50000,
            'remaining_amount' => 50000,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'payment_schedule_id' => $scheduleId,
            'order_id' => $orderId,
            'contractor_id' => $contractor->id,
            'party' => 'customer',
            'amount' => 50000,
            'payment_date' => '2026-06-12',
            'transaction_reference' => 'ПП-050',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService))
            ->build($contractor->id, null, null, null);

        $row = $report['as_customer']['rows'][0];
        $this->assertFalse($row['has_upd']);
        $this->assertSame(100000.0, $row['accrued']);
        $this->assertSame(50000.0, $row['paid']);
        $this->assertSame(50000.0, $row['balance']);
        $this->assertSame('receivable', $row['balance_status']);
        $this->assertSame('Долг', $row['balance_label']);
    }
}
