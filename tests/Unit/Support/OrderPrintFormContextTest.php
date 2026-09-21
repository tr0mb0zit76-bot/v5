<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\PrintFormBasicTerm;
use App\Support\OrderPrintFormContext;
use PHPUnit\Framework\TestCase;

class OrderPrintFormContextTest extends TestCase
{
    public function test_intercompany_subcontract_uses_carrier_party_constant(): void
    {
        $context = OrderPrintFormContext::forIntercompanySubcontract();

        $this->assertSame(PrintFormBasicTerm::PARTY_CARRIER, $context->printParty);
        $this->assertTrue($context->intercompanySubcontract);
    }
}
