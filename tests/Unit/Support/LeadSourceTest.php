<?php

namespace Tests\Unit\Support;

use App\Support\LeadSource;
use PHPUnit\Framework\TestCase;

class LeadSourceTest extends TestCase
{
    public function test_label_returns_russian_name_for_known_source(): void
    {
        $this->assertSame('Входящий', LeadSource::label('inbound'));
        $this->assertSame('Действующий клиент', LeadSource::label('existing_customer'));
        $this->assertSame('Повторная обработка базы', LeadSource::label('base_reprocessing'));
    }

    public function test_label_returns_original_value_for_unknown_source(): void
    {
        $this->assertSame('custom_channel', LeadSource::label('custom_channel'));
    }

    public function test_label_returns_null_for_empty_value(): void
    {
        $this->assertNull(LeadSource::label(null));
        $this->assertNull(LeadSource::label(''));
    }

    public function test_options_match_sources(): void
    {
        $options = LeadSource::options();

        $this->assertSame('inbound', $options[0]['value']);
        $this->assertSame('Входящий', $options[0]['label']);
        $this->assertCount(count(LeadSource::SOURCES), $options);
    }
}
