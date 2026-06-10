<?php

namespace Tests\Unit;

use App\Services\PrintFormVariableCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrintFormVariableCatalogTest extends TestCase
{
    #[Test]
    public function order_options_are_sorted_alphabetically_by_label(): void
    {
        $options = (new PrintFormVariableCatalog)->orderOptions();
        $labels = array_column($options, 'label');
        $sorted = $labels;
        sort($sorted, SORT_FLAG_CASE | SORT_STRING);

        $this->assertSame($sorted, $labels);
    }

    #[Test]
    public function order_options_include_trailer_placeholders(): void
    {
        $values = array_column((new PrintFormVariableCatalog)->orderOptions(), 'value');

        $this->assertContains('vehicle.trailer_brand', $values);
        $this->assertContains('vehicle.trailer_plate', $values);
    }
}
