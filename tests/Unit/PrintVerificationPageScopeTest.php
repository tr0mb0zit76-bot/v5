<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Support\PrintVerificationPageScope;
use Tests\TestCase;

class PrintVerificationPageScopeTest extends TestCase
{
    public function test_resolves_party_from_metadata(): void
    {
        $this->assertSame('customer', PrintVerificationPageScope::partyFromMetadata(['party' => 'customer']));
        $this->assertSame('carrier', PrintVerificationPageScope::partyFromMetadata(['party' => 'carrier']));
        $this->assertNull(PrintVerificationPageScope::partyFromMetadata([]));
    }

    public function test_customer_scope_shows_only_customer(): void
    {
        $customer = new Contractor(['name' => 'ООО Заказчик']);
        $carrier = new Contractor(['name' => 'ООО Перевозчик']);

        $rows = PrintVerificationPageScope::counterpartyRows('customer', $customer, $carrier);

        $this->assertCount(1, $rows);
        $this->assertSame('Заказчик', $rows[0]['label']);
        $this->assertSame('ООО Заказчик', $rows[0]['name']);
    }

    public function test_carrier_scope_shows_only_carrier(): void
    {
        $customer = new Contractor(['name' => 'ООО Заказчик']);
        $carrier = new Contractor(['name' => 'ООО Перевозчик']);

        $rows = PrintVerificationPageScope::counterpartyRows('carrier', $customer, $carrier);

        $this->assertCount(1, $rows);
        $this->assertSame('Перевозчик', $rows[0]['label']);
        $this->assertSame('ООО Перевозчик', $rows[0]['name']);
    }

    public function test_unknown_party_hides_counterparties(): void
    {
        $customer = new Contractor(['name' => 'ООО Заказчик']);
        $carrier = new Contractor(['name' => 'ООО Перевозчик']);

        $this->assertSame([], PrintVerificationPageScope::counterpartyRows(null, $customer, $carrier));
    }
}
