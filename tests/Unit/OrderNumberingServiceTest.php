<?php

namespace Tests\Unit;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use App\Models\OrderNumberingRule;
use App\Services\OrderNumberingService;
use Carbon\Carbon;
use Tests\TestCase;

class OrderNumberingServiceTest extends TestCase
{
    public function test_composes_sequence_text_month_pattern(): void
    {
        $rule = new OrderNumberingRule([
            'cipher' => 'exwill',
            'separator' => '-',
            'prefix_type' => OrderNumberSegmentType::Sequence,
            'body_type' => OrderNumberSegmentType::Text,
            'body_value' => 'X',
            'suffix_type' => OrderNumberSegmentType::Month,
            'sequence_pad' => 0,
            'sequence_scope' => OrderNumberSequenceScope::Month,
        ]);

        $service = app(OrderNumberingService::class);
        $at = Carbon::parse('2026-04-15');

        $this->assertSame('1-X-04', $service->composeNumber($rule, 1, $at));
        $this->assertSame('2-X-04', $service->composeNumber($rule, 2, $at));

        $august = Carbon::parse('2026-08-20');
        $this->assertSame('3-X-08', $service->composeNumber($rule, 3, $august));
    }

    public function test_resolves_company_code_from_cipher(): void
    {
        $rule = new OrderNumberingRule([
            'cipher' => 'exwill-main',
        ]);

        $this->assertSame('EXWILLMAIN', app(OrderNumberingService::class)->resolveCompanyCode($rule));
    }
}
