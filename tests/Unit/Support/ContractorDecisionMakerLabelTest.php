<?php

namespace Tests\Unit\Support;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Support\ContractorDecisionMakerLabel;
use Tests\TestCase;

class ContractorDecisionMakerLabelTest extends TestCase
{
    public function test_prefers_decision_maker_contact(): void
    {
        $contractor = new Contractor([
            'name' => 'ООО Клиент',
            'contact_person' => 'Секретарь',
        ]);
        $contractor->setRelation('contacts', collect([
            new ContractorContact([
                'full_name' => 'Петров Пётр',
                'position' => 'Директор',
                'is_decision_maker' => true,
            ]),
        ]));

        $this->assertSame(
            'Петров Пётр, Директор',
            ContractorDecisionMakerLabel::resolve($contractor),
        );
    }

    public function test_falls_back_to_contractor_contact_person(): void
    {
        $contractor = new Contractor([
            'contact_person' => 'Иванова Анна',
            'contact_person_position' => 'Логист',
        ]);
        $contractor->setRelation('contacts', collect());

        $this->assertSame(
            'Иванова Анна, Логист',
            ContractorDecisionMakerLabel::resolve($contractor),
        );
    }
}
