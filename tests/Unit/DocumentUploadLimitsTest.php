<?php

namespace Tests\Unit;

use App\Support\DocumentUploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentUploadLimitsTest extends TestCase
{
    public function test_for_shared_inertia_returns_positive_budget_fields(): void
    {
        config([
            'documents.bytes_per_page' => 600 * 1024,
            'documents.max_pages_cap' => 200,
        ]);

        $limits = DocumentUploadLimits::forSharedInertia();

        $this->assertSame(600 * 1024, $limits['bytes_per_page']);
        $this->assertSame(200, $limits['max_pages_cap']);
        $this->assertGreaterThan(1024 * 1024, $limits['policy_max_bytes']);
        $this->assertGreaterThanOrEqual($limits['policy_max_bytes'], $limits['absolute_max_bytes']);
        $this->assertStringContainsString('estimate-upload-budget', (string) ($limits['estimate_budget_url'] ?? ''));
        $this->assertStringContainsString('600 КиБ', $limits['hint_ru']);
    }

    #[DataProvider('humanSizeExamplesProvider')]
    public function test_hint_ru_uses_mib_not_zero_for_default_policy(int $bytesPerPage, int $cap): void
    {
        config([
            'documents.bytes_per_page' => $bytesPerPage,
            'documents.max_pages_cap' => $cap,
        ]);

        $limits = DocumentUploadLimits::forSharedInertia();

        $this->assertGreaterThanOrEqual($bytesPerPage, $limits['absolute_max_bytes']);
        $this->assertMatchesRegularExpression('/\d+/', $limits['hint_ru']);
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public static function humanSizeExamplesProvider(): array
    {
        return [
            [600 * 1024, 200],
            [1024, 1],
        ];
    }
}
