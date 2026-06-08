<?php

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Support\DocumentRegistryDocumentLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentRegistryDocumentLabelTest extends TestCase
{
    #[Test]
    public function it_builds_label_with_carrier_slot_and_contractor_name(): void
    {
        $document = new OrderDocument([
            'number' => null,
            'original_name' => 'Заявка с перевозчиком ВЭД.docx',
        ]);

        $label = DocumentRegistryDocumentLabel::build(
            $document,
            ['carrier_slot' => 2, 'carrier_contractor_id' => 15],
            [15 => 'ООО ТрансЛайн'],
        );

        $this->assertSame('Заявка с перевозчиком ВЭД.docx · исп. 2 · ООО ТрансЛайн', $label);
    }
}
