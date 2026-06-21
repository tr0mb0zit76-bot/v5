<?php

namespace Tests\Unit\Services\Commercial;

use App\Services\Commercial\LeadProposalPdfService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadProposalPdfServiceTest extends TestCase
{
    public function test_converts_html_via_gotenberg_chromium_endpoint(): void
    {
        config([
            'document_preview.driver' => 'gotenberg',
            'document_preview.gotenberg.url' => 'http://gotenberg.test',
            'document_preview.gotenberg.timeout' => 30,
        ]);

        Http::fake([
            'http://gotenberg.test/forms/chromium/convert/html' => Http::response('%PDF-1.4 mock', 200),
        ]);

        $service = new LeadProposalPdfService;
        $pdf = $service->convertHtmlToPdf('<html><body>test</body></html>', 'offer.pdf');

        $this->assertSame('%PDF-1.4 mock', $pdf);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://gotenberg.test/forms/chromium/convert/html';
        });
    }

    public function test_returns_null_when_gotenberg_disabled(): void
    {
        config(['document_preview.driver' => 'html']);

        $service = new LeadProposalPdfService;
        $pdf = $service->convertHtmlToPdf('<html></html>');

        $this->assertNull($pdf);
    }
}
