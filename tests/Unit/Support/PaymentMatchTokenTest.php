<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Support\PaymentMatchToken;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentMatchTokenTest extends TestCase
{
    public function test_builds_and_parses_token(): void
    {
        $order = new Order(['order_number' => 'АС-2608-0042']);
        $order->id = 10;
        $schedule = new PaymentSchedule([
            'party' => 'carrier',
        ]);
        $schedule->installment_sequence = 2;
        $schedule->setRelation('order', $order);

        $token = PaymentMatchToken::forSchedule($schedule);
        $this->assertSame('CRM:АС-2608-0042:P2', $token);

        $parsed = PaymentMatchToken::parse('Оплата по заказу АС-2608-0042 '.$token);
        $this->assertNotNull($parsed);
        $this->assertSame('АС-2608-0042', $parsed['order_number']);
        $this->assertSame('P', $parsed['side']);
        $this->assertSame(2, $parsed['sequence']);
    }

    public function test_assert_blocks_outgoing_bank_without_token(): void
    {
        config(['one_c.payment_token.enforce_outgoing_bank' => true]);

        $order = new Order(['order_number' => 'АС-2608-0001']);
        $order->id = 1;
        $schedule = new PaymentSchedule(['party' => 'carrier', 'installment_sequence' => 1]);
        $schedule->setRelation('order', $order);

        $this->expectException(InvalidArgumentException::class);
        PaymentMatchToken::assertOutgoingBankPurpose($schedule, 'bank_transfer', 'Оплата перевозчику');
    }

    public function test_assert_allows_matching_token(): void
    {
        config(['one_c.payment_token.enforce_outgoing_bank' => true]);

        $order = new Order(['order_number' => 'АС-2608-0001']);
        $order->id = 1;
        $schedule = new PaymentSchedule(['party' => 'carrier', 'installment_sequence' => 1]);
        $schedule->setRelation('order', $order);

        PaymentMatchToken::assertOutgoingBankPurpose(
            $schedule,
            'bank_transfer',
            PaymentMatchToken::purposeLine($schedule),
        );

        $this->assertTrue(true);
    }

    public function test_cash_skips_token_stop(): void
    {
        config(['one_c.payment_token.enforce_outgoing_bank' => true]);

        $order = new Order(['order_number' => 'АС-2608-0001']);
        $order->id = 1;
        $schedule = new PaymentSchedule(['party' => 'carrier', 'installment_sequence' => 1]);
        $schedule->setRelation('order', $order);

        PaymentMatchToken::assertOutgoingBankPurpose($schedule, 'cash', null);
        $this->assertTrue(true);
    }
}
