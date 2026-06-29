<?php

namespace Tests\Unit;

use App\Enums\SalesTrainerDialogQuality;
use App\Models\Role;
use App\Models\SalesScript;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\TrainerCoachingInsightsService;
use App\Services\SalesScripts\TrainerDialogLoopDetector;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrainerCoachingInsightsServiceTest extends TestCase
{
    #[Test]
    public function it_denies_access_without_trainer_analytics_area(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            $this->markTestSkipped('sales_script_play_sessions table is missing.');
        }

        $user = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'manager',
                'display_name' => 'Manager',
                'permissions' => [],
                'visibility_areas' => ['sales_assistant_trainer'],
            ])->id,
        ]);

        $service = new TrainerCoachingInsightsService(new TrainerDialogLoopDetector);
        $result = $service->insights($user);

        $this->assertFalse($result['available']);
    }

    #[Test]
    public function it_returns_coaching_summary_for_trainer_analytics_user(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            $this->markTestSkipped('sales_script_play_sessions table is missing.');
        }

        $user = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'coach',
                'display_name' => 'Coach',
                'permissions' => [],
                'visibility_areas' => ['sales_assistant_trainer_analytics'],
            ])->id,
        ]);

        $script = SalesScript::query()->create([
            'title' => 'Тестовый сценарий',
            'description' => null,
            'channel' => 'phone',
            'tags' => [],
        ]);

        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => now(),
            'is_active' => true,
            'entry_node_key' => 'start',
        ]);

        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'is_trainer' => true,
            'trainer_profile_key' => 'skeptic',
            'trainer_profile_title' => 'Скептик',
            'trainer_dialog_quality' => SalesTrainerDialogQuality::Stuck->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lines = [
            ['role' => 'user', 'content' => 'Сколько стоит доставка?'],
            ['role' => 'assistant', 'content' => 'Зависит от маршрута, уточните пункты.'],
            ['role' => 'user', 'content' => 'Сколько стоит доставка до Москвы?'],
            ['role' => 'assistant', 'content' => 'Зависит от маршрута, уточните пункты отправки.'],
        ];

        foreach ($lines as $line) {
            SalesScriptTrainerMessage::query()->create([
                'sales_script_play_session_id' => $session->id,
                'user_id' => $user->id,
                'role' => $line['role'],
                'content' => $line['content'],
            ]);
        }

        $service = new TrainerCoachingInsightsService(new TrainerDialogLoopDetector);
        $result = $service->insights($user, 30);

        $this->assertTrue($result['available']);
        $this->assertSame('self', $result['scope']);
        $this->assertSame(1, $result['summary']['total_sessions']);
        $this->assertSame(1, $result['summary']['stuck_sessions']);
        $this->assertNotEmpty($result['recommendations']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['loop_detected_sessions']);
    }
}
