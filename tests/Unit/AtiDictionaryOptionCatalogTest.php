<?php

namespace Tests\Unit;

use App\Support\AtiDictionaryOptionCatalog;
use Tests\TestCase;

class AtiDictionaryOptionCatalogTest extends TestCase
{
    public function test_fallback_package_type_options_include_barrels(): void
    {
        $labels = array_column(AtiDictionaryOptionCatalog::fallbackPackageTypeOptions(), 'label');
        $codes = array_column(AtiDictionaryOptionCatalog::fallbackPackageTypeOptions(), 'code');

        $this->assertContains('Бочки', $labels);
        $this->assertContains('barrel', $codes);
    }
}
