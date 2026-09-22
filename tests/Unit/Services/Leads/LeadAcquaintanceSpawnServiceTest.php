<?php

namespace Tests\Unit\Services\Leads;

use App\Models\BusinessProcess;
use App\Services\Leads\LeadAcquaintanceSpawnService;
use Tests\TestCase;

class LeadAcquaintanceSpawnServiceTest extends TestCase
{
    public function test_target_process_lookup_accepts_prod_renamed_slug(): void
    {
        $process = BusinessProcess::query()->where('slug', 'transport-intake')->first();

        if ($process === null) {
            $process = BusinessProcess::query()->create([
                'name' => 'От запроса до заказа',
                'slug' => 'transport-intake',
                'is_active' => true,
            ]);
        }

        $process->forceFill([
            'slug' => 'ot-zaprosa-do-zakaza',
            'name' => 'От запроса до заказа',
        ])->save();

        $found = BusinessProcess::query()
            ->whereIn('slug', LeadAcquaintanceSpawnService::TARGET_PROCESS_SLUG_ALIASES)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [LeadAcquaintanceSpawnService::TARGET_PROCESS_SLUG])
            ->first();

        $this->assertNotNull($found);
        $this->assertSame('ot-zaprosa-do-zakaza', $found->slug);
    }
}
