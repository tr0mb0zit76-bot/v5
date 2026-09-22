<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCPublicationCatalog;
use Tests\TestCase;

/**
 * Рефы номенклатуры/валюты не шарятся между ИБ — иначе «Объект не найден».
 */
class OneCPublicationCatalogRefsTest extends TestCase
{
    public function test_each_live_publication_has_distinct_teu_and_currency_defaults(): void
    {
        $catalog = app(OneCPublicationCatalog::class);

        $aa = $catalog->get(OneCPublicationCatalog::CODE_AUTALLIANCE);
        $gross = $catalog->get(OneCPublicationCatalog::CODE_GROSS);
        $profsfera = $catalog->get(OneCPublicationCatalog::CODE_PROFSFERA);

        $this->assertSame('9ec829b8-632e-11f1-8745-fa163ea037a3', $aa['service_nomenclature_ref']);
        $this->assertSame('00-00000001', $aa['service_nomenclature_code']);
        $this->assertSame('69e038af-320f-11f1-acc9-b69a48ddb3f4', $aa['currency_ref']);

        $this->assertSame('b35e5374-bae4-11ef-89a3-dc68443ee9e4', $gross['service_nomenclature_ref']);
        $this->assertSame('00-00000001', $gross['service_nomenclature_code']);
        $this->assertSame('0601b6db-556c-11eb-8132-0050569f5448', $gross['currency_ref']);

        $this->assertSame('af537684-63c4-11f1-8ae7-fa163eafb81d', $profsfera['service_nomenclature_ref']);
        $this->assertSame('00-00000002', $profsfera['service_nomenclature_code']);
        $this->assertSame('69e038af-320f-11f1-acc9-b69a48ddb3f4', $profsfera['currency_ref']);

        $this->assertNotSame($aa['service_nomenclature_ref'], $gross['service_nomenclature_ref']);
        $this->assertNotSame($aa['service_nomenclature_ref'], $profsfera['service_nomenclature_ref']);
        $this->assertNotSame($gross['service_nomenclature_ref'], $profsfera['service_nomenclature_ref']);
        $this->assertNotSame($aa['currency_ref'], $gross['currency_ref']);
    }
}
