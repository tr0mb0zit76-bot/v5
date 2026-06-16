<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderClipboardSummaryFormatter;
use App\Support\OrderFleetTransportDetailsResolver;
use Tests\TestCase;

class OrderFleetTransportDetailsResolverTest extends TestCase
{
    public function test_resolves_carrier_portal_submission_plates_for_order(): void
    {
        $order = new Order([
            'performers' => [[
                'carrier_portal_submission' => [
                    'tractor_brand' => 'MAN',
                    'tractor_plate' => 'A123BC77',
                    'trailer_brand' => 'Schmitz',
                    'trailer_plate' => 'BB123477',
                    'driver_full_name' => 'Иванов Иван',
                ],
            ]],
        ]);

        $details = app(OrderFleetTransportDetailsResolver::class)->resolveForOrder($order);

        $this->assertSame('A123BC77', $details['tractor_plate']);
        $this->assertSame('BB123477', $details['trailer_plate']);
        $this->assertSame('Иванов Иван', $details['driver_name']);

        $summary = OrderClipboardSummaryFormatter::format(
            'AA',
            'Клиент',
            '100',
            '2026-05-01',
            1000,
            'no_vat',
            'Москва',
            'Казань',
            $details['tractor_brand'],
            $details['tractor_plate'],
            $details['trailer_brand'],
            $details['trailer_plate'],
            $details['driver_name'],
        );

        $this->assertStringContainsString('MAN / A123BC77 / Schmitz / BB123477', $summary);
    }

    public function test_resolves_split_carrier_portal_submission_from_grid_row(): void
    {
        $details = app(OrderFleetTransportDetailsResolver::class)->resolveForGridRow([
            'performers' => [[
                'carrier_mode' => 'split',
                'split_carriers' => [[
                    'carrier_portal_submission' => [
                        'tractor_plate' => 'X111XX77',
                        'trailer_plate' => 'YY222277',
                    ],
                ]],
            ]],
        ]);

        $this->assertSame('X111XX77', $details['tractor_plate']);
        $this->assertSame('YY222277', $details['trailer_plate']);
    }
}
