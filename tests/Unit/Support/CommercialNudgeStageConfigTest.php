<?php

namespace Tests\Unit\Support;

use App\Models\BusinessProcessStage;
use App\Support\CommercialNudgeStageConfig;
use App\Support\CommercialNudgeType;
use Tests\TestCase;

class CommercialNudgeStageConfigTest extends TestCase
{
    public function test_uses_explicit_nudge_triggers_when_set(): void
    {
        $stage = new BusinessProcessStage([
            'nudge_triggers' => [CommercialNudgeType::LedgerIdle->value],
        ]);

        $enabled = CommercialNudgeStageConfig::enabledTypes($stage);

        $this->assertCount(1, $enabled);
        $this->assertSame(CommercialNudgeType::LedgerIdle, $enabled[0]);
    }

    public function test_falls_back_to_default_triggers_when_empty(): void
    {
        $stage = new BusinessProcessStage([
            'nudge_triggers' => null,
        ]);

        $enabled = array_map(fn (CommercialNudgeType $type): string => $type->value, CommercialNudgeStageConfig::enabledTypes($stage));

        $this->assertContains(CommercialNudgeType::NextContactMissed->value, $enabled);
    }
}
