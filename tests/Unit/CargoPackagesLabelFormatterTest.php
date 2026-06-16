<?php

namespace Tests\Unit;

use App\Models\Cargo;
use App\Models\Order;
use App\Services\OrderPrintFormDraftService;
use App\Support\CargoPackagesLabelFormatter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CargoPackagesLabelFormatterTest extends TestCase
{
    public function test_count_label_uses_russian_plural_forms(): void
    {
        $this->assertSame('1 место', CargoPackagesLabelFormatter::countLabel(1));
        $this->assertSame('2 места', CargoPackagesLabelFormatter::countLabel(2));
        $this->assertSame('5 мест', CargoPackagesLabelFormatter::countLabel(5));
        $this->assertSame('11 мест', CargoPackagesLabelFormatter::countLabel(11));
        $this->assertSame('21 место', CargoPackagesLabelFormatter::countLabel(21));
        $this->assertSame('', CargoPackagesLabelFormatter::countLabel(0));
    }

    public function test_pack_type_label_reads_packaging_fields(): void
    {
        $this->assertSame(
            'Паллеты',
            CargoPackagesLabelFormatter::packTypeLabel(new Cargo([
                'pack_type_label' => 'Паллеты',
            ])),
        );

        $this->assertSame(
            'Ящики',
            CargoPackagesLabelFormatter::packTypeLabel(new Cargo([
                'packing_type' => 'Ящики',
            ])),
        );
    }

    public function test_cargo_table_rows_include_packages_label_and_pack_type(): void
    {
        $service = app(OrderPrintFormDraftService::class);
        $method = new \ReflectionMethod($service, 'buildCargoTableRowsForTemplate');
        $method->setAccessible(true);

        $order = new Order;
        $order->setRelation('cargoItems', new Collection([
            new Cargo([
                'name' => 'Кирпич',
                'package_count' => 5,
                'pack_type_label' => 'Паллеты',
            ]),
        ]));

        /** @var list<array<string, string>> $rows */
        $rows = $method->invoke($service, $order->cargoItems, $order, null);

        $this->assertSame('5', $rows[0]['cargo_row_packages']);
        $this->assertSame('5 мест', $rows[0]['cargo_row_packages_label']);
        $this->assertSame('Паллеты', $rows[0]['cargo_row_pack_type']);
    }
}
