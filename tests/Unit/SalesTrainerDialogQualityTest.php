<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SalesTrainerDialogQuality;
use Tests\TestCase;

class SalesTrainerDialogQualityTest extends TestCase
{
    public function test_labels_are_non_empty_russian_strings(): void
    {
        foreach (SalesTrainerDialogQuality::cases() as $case) {
            $label = $case->label();
            $this->assertGreaterThan(3, mb_strlen($label));
        }
    }
}
