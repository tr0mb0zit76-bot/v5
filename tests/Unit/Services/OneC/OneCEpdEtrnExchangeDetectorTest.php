<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCEpdEtrnExchangeDetector;
use PHPUnit\Framework\TestCase;

class OneCEpdEtrnExchangeDetectorTest extends TestCase
{
    public function test_detects_title_2_as_carrier_acceptance(): void
    {
        $detector = new OneCEpdEtrnExchangeDetector;

        $this->assertTrue($detector->titleIsCarrierAcceptanceOrLater('ЭТрН_Титул2'));
        $this->assertTrue($detector->titleIsCarrierAcceptanceOrLater('ЭТрН_Титул3'));
        $this->assertFalse($detector->titleIsCarrierAcceptanceOrLater('ЭТрН_Титул1'));
        $this->assertFalse($detector->titleIsCarrierAcceptanceOrLater(''));
    }

    public function test_exchange_completed_from_document_fields(): void
    {
        $detector = new OneCEpdEtrnExchangeDetector;

        $this->assertTrue($detector->exchangeWithCarrierCompleted([
            'ТекущийПолученныйТитул' => 'ЭТрН_Титул2',
            'ТекущийШаг' => 'Погрузка',
            'ТекущийШагВыполнен' => true,
        ]));

        $this->assertTrue($detector->exchangeWithCarrierCompleted([
            'ТекущийПолученныйТитул' => '',
            'ТитулПеревозчикаПриемкаИдентификаторФайла' => 'ON_TRNACLPPRIN_1',
        ]));

        $this->assertFalse($detector->exchangeWithCarrierCompleted([
            'ТекущийПолученныйТитул' => 'ЭТрН_Титул1',
            'ТекущийШаг' => 'Оформление',
            'ТекущийШагВыполнен' => false,
        ]));
    }

    public function test_meta_from_document_extracts_waybill_and_parties(): void
    {
        $detector = new OneCEpdEtrnExchangeDetector;
        $meta = $detector->metaFromDocument([
            'ТекущийПолученныйТитул' => 'ЭТрН_Титул2',
            'ТекущийТитул' => 'ЭТрН_Титул2',
            'ТекущийШаг' => 'Погрузка',
            'ТекущийШагВыполнен' => true,
            'ТитулГрузоотправителяТранспортнаяНакладнаяНомер' => '1',
            'ТитулГрузоотправителяТранспортнаяНакладнаяДата' => '2026-09-07T00:00:00',
            'СсылкаТитулГрузоотправителяЗаказчик' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'СсылкаТитулГрузоотправителяПеревозчик' => '11111111-2222-3333-4444-555555555555',
            'ТитулПеревозчикаПриемкаИдентификаторФайла' => 'ON_FILE',
        ]);

        $this->assertTrue($meta['exchange_with_carrier']);
        $this->assertSame('1', $meta['waybill_number']);
        $this->assertSame('2026-09-07', $meta['waybill_date']);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $meta['customer_ref']);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $meta['carrier_ref']);
    }
}
