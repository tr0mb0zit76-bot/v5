<?php

namespace Tests\Unit\Support;

use App\Support\OrderCargoItemsPayloadNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderCargoItemsPayloadNormalizerTest extends TestCase
{
    #[Test]
    public function blank_row_has_no_substance(): void
    {
        $this->assertFalse(OrderCargoItemsPayloadNormalizer::cargoItemHasSubstance([
            'name' => '',
            'cargo_type' => 'general',
        ]));
        $this->assertFalse(OrderCargoItemsPayloadNormalizer::cargoItemRequiresName([
            'name' => '',
            'cargo_type' => 'general',
        ]));
    }

    #[Test]
    public function weight_without_name_requires_name(): void
    {
        $item = [
            'name' => '  ',
            'weight_value' => 658,
            'cargo_type' => 'general',
        ];

        $this->assertTrue(OrderCargoItemsPayloadNormalizer::cargoItemHasSubstance($item));
        $this->assertTrue(OrderCargoItemsPayloadNormalizer::cargoItemRequiresName($item));
    }

    #[Test]
    public function named_item_does_not_require_name(): void
    {
        $item = [
            'name' => 'Автозапчасти',
            'weight_value' => 658,
        ];

        $this->assertTrue(OrderCargoItemsPayloadNormalizer::cargoItemHasSubstance($item));
        $this->assertFalse(OrderCargoItemsPayloadNormalizer::cargoItemRequiresName($item));
    }
}
