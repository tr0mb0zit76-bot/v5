<?php

namespace Tests\Unit;

use App\Support\PaymentFormDictionary;
use Tests\TestCase;

class PaymentFormDictionaryJournalLabelTest extends TestCase
{
    public function test_journal_label_groups_vat_rates_and_cash(): void
    {
        $this->assertSame('Наличка', PaymentFormDictionary::journalLabelForCode('cash'));
        $this->assertSame('Без НДС', PaymentFormDictionary::journalLabelForCode('no_vat'));
        $this->assertSame('НДС', PaymentFormDictionary::journalLabelForCode('vat'));
        $this->assertSame('Разные', PaymentFormDictionary::journalLabelForCode('mixed'));
    }

    public function test_journal_filter_labels_are_simplified(): void
    {
        $this->assertSame(
            ['Наличка', 'НДС', 'Без НДС', 'Разные', '—'],
            PaymentFormDictionary::journalFilterLabels(),
        );
    }
}
