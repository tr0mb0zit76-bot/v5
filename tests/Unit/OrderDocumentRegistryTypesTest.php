<?php

namespace Tests\Unit;

use App\Support\OrderDocumentRegistryTypes;
use Tests\TestCase;

class OrderDocumentRegistryTypesTest extends TestCase
{
    public function test_includes_etrn_type(): void
    {
        $this->assertContains('etrn', OrderDocumentRegistryTypes::values());
    }
}
