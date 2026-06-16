<?php

namespace Tests\Unit;

use App\Support\AtiDictionaryOptionCatalog;
use ReflectionMethod;
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

    public function test_merge_fallback_restores_missing_pack_types_from_database_subset(): void
    {
        $method = new ReflectionMethod(AtiDictionaryOptionCatalog::class, 'mergeFallbackOptions');
        $method->setAccessible(true);

        /** @var list<array{value:int, code:string|null, label:string, ati_id:int|null}> $options */
        $options = $method->invoke(null, [
            ['value' => 6, 'code' => 'barrel', 'label' => 'Бочки', 'ati_id' => 6],
        ], AtiDictionaryOptionCatalog::fallbackPackageTypeOptions());

        $labels = array_column($options, 'label');

        $this->assertCount(6, $options);
        $this->assertContains('Паллета', $labels);
        $this->assertContains('Короб', $labels);
        $this->assertContains('Бочки', $labels);
    }
}
