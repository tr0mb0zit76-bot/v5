<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\OneCEpdRegistryEntry;
use App\Services\OneC\OneCEpdDocumentTypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OneCEpdDocumentTypeResolverTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_resolves_document_types(?string $refType, ?string $fullName, ?string $synonym, string $code): void
    {
        $resolved = (new OneCEpdDocumentTypeResolver)->resolve($refType, $fullName, $synonym);

        $this->assertSame($code, $resolved['code']);
        $this->assertNotSame('', $resolved['label']);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: string}>
     */
    public static function cases(): array
    {
        return [
            'etrn odata' => [
                'StandardODATA.Document_ЭлектроннаяТранспортнаяНакладная',
                null,
                null,
                OneCEpdRegistryEntry::TYPE_ETRN,
            ],
            'expedition order meta' => [
                'UnavailableEntities.UnavailableEntity_25de02b8-9393-4c1a-8d56-e3cfe7f840cb',
                'Документ.ЭлектронноеПоручениеЭкспедитору',
                'Электронное поручение экспедитору',
                OneCEpdRegistryEntry::TYPE_EXPEDITION_ORDER,
            ],
            'expedition receipt meta' => [
                'UnavailableEntities.UnavailableEntity_075950fc-13b5-4027-9ef9-9d08696a2960',
                'Документ.ЭлектроннаяЭкспедиторскаяРасписка',
                'Электронная экспедиторская расписка',
                OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT,
            ],
            'e order' => [
                'StandardODATA.Document_ЭлектронныйЗаказЗаявка',
                null,
                null,
                OneCEpdRegistryEntry::TYPE_E_ORDER,
            ],
            'unknown' => [
                'UnavailableEntities.UnavailableEntity_deadbeef',
                null,
                null,
                OneCEpdRegistryEntry::TYPE_UNKNOWN,
            ],
        ];
    }
}
