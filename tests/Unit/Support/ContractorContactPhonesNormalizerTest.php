<?php

namespace Tests\Unit\Support;

use App\Support\ContractorContactPhonesNormalizer;
use PHPUnit\Framework\TestCase;

class ContractorContactPhonesNormalizerTest extends TestCase
{
    public function test_it_mirrors_primary_phone_from_phones_list(): void
    {
        $result = ContractorContactPhonesNormalizer::normalizeContactPhones([
            'phones' => [
                ['number' => '+7 111', 'kind' => 'work', 'is_primary' => false],
                ['number' => '+7 222', 'kind' => 'personal', 'is_primary' => true],
            ],
        ]);

        $this->assertSame('+7 222', $result['phone']);
        $this->assertCount(2, $result['phones']);
        $this->assertTrue($result['phones'][1]['is_primary']);
        $this->assertFalse($result['phones'][0]['is_primary']);
    }

    public function test_it_builds_phones_from_legacy_phone_field(): void
    {
        $result = ContractorContactPhonesNormalizer::normalizeContactPhones([
            'phone' => '+7 999 000-00-00',
        ]);

        $this->assertSame('+7 999 000-00-00', $result['phone']);
        $this->assertSame([
            [
                'number' => '+7 999 000-00-00',
                'kind' => 'work',
                'is_primary' => true,
            ],
        ], $result['phones']);
    }

    public function test_it_marks_first_phone_primary_when_none_selected(): void
    {
        $result = ContractorContactPhonesNormalizer::normalizeContactPhones([
            'phones' => [
                ['number' => '+7 111', 'kind' => 'mobile'],
                ['number' => '+7 222', 'kind' => 'other'],
            ],
        ]);

        $this->assertTrue($result['phones'][0]['is_primary']);
        $this->assertFalse($result['phones'][1]['is_primary']);
        $this->assertSame('+7 111', $result['phone']);
    }
}
