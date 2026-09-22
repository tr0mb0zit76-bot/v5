<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCPublicationCatalog;
use Tests\TestCase;

class OneCGrossServiceNomenclatureConfigTest extends TestCase
{
    public function test_gross_has_own_teu_ref_different_from_autalliance(): void
    {
        $catalog = app(OneCPublicationCatalog::class);
        $gross = $catalog->get(OneCPublicationCatalog::CODE_GROSS);
        $aa = $catalog->get(OneCPublicationCatalog::CODE_AUTALLIANCE);

        $this->assertNotSame('', $gross['service_nomenclature_ref']);
        $this->assertSame('00-00000001', $gross['service_nomenclature_code']);
        $this->assertSame('b35e5374-bae4-11ef-89a3-dc68443ee9e4', $gross['service_nomenclature_ref']);
        $this->assertNotSame($aa['service_nomenclature_ref'], $gross['service_nomenclature_ref']);
    }
}
