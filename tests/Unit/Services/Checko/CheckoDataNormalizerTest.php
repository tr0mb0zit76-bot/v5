<?php

namespace Tests\Unit\Services\Checko;

use App\Services\Checko\CheckoDataNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoDataNormalizerTest extends TestCase
{
    #[Test]
    public function it_prefers_egrul_status_over_meta_ok(): void
    {
        $normalizer = new CheckoDataNormalizer;

        $bundle = [
            'company' => [
                'ok' => true,
                'body' => [
                    'meta' => ['status' => 'ok'],
                    'data' => [
                        'Статус' => 'Действующая организация',
                    ],
                ],
            ],
            'finances' => ['ok' => false, 'body' => null],
            'enforcements' => ['ok' => false, 'body' => null],
            'legal_defendant' => ['ok' => false, 'body' => null],
            'legal_plaintiff' => ['ok' => false, 'body' => null],
        ];

        $result = $normalizer->normalize($bundle);

        $this->assertSame('Действующая организация', $result['status_text']);
    }

    #[Test]
    public function it_finds_status_in_nested_data_keys(): void
    {
        $normalizer = new CheckoDataNormalizer;

        $bundle = [
            'company' => [
                'ok' => true,
                'body' => [
                    'meta' => ['status' => 'ok'],
                    'data' => [
                        'Инфо' => [
                            'СтатусОрганизации' => 'Действующая организация',
                        ],
                    ],
                ],
            ],
            'finances' => ['ok' => false, 'body' => null],
            'enforcements' => ['ok' => false, 'body' => null],
            'legal_defendant' => ['ok' => false, 'body' => null],
            'legal_plaintiff' => ['ok' => false, 'body' => null],
        ];

        $result = $normalizer->normalize($bundle);

        $this->assertSame('Действующая организация', $result['status_text']);
    }
}
