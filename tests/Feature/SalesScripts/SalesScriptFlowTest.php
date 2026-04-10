<?php

namespace Tests\Feature\SalesScripts;

use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesScriptFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
            });
        }
    }

    public function test_guest_is_redirected_from_scripts_index(): void
    {
        $this->get(route('scripts.index'))->assertRedirect();
    }

    public function test_user_without_scripts_area_cannot_access_scripts_index(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'no_scripts',
            'display_name' => 'No scripts',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('scripts.index'))->assertForbidden();
    }

    public function test_manager_can_run_demo_script_and_complete_session(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager_scripts',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $versionId = (int) SalesScriptVersion::query()->value('id');
        $positiveId = (int) SalesScriptReactionClass::query()->where('key', 'positive_signal')->value('id');

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $versionId,
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame($user->id, $session->user_id);

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => $positiveId,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $session->refresh();
        $this->assertSame('end', $session->currentNode?->client_key);

        $this->actingAs($user)
            ->post(route('scripts.sessions.complete', $session), [
                'outcome' => 'progress',
                'primary_reaction_class_id' => null,
                'notes' => 'Тестовая сессия',
            ])
            ->assertRedirect(route('scripts.index'));

        $session->refresh();
        $this->assertNotNull($session->completed_at);
        $this->assertSame('progress', $session->outcome->value);
        $this->assertGreaterThanOrEqual(6, $session->events()->count());
    }

    public function test_user_cannot_advance_foreign_session(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'm1',
            'display_name' => 'M1',
            'visibility_areas' => json_encode(['scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = User::factory()->create(['role_id' => $roleId, 'email_verified_at' => now()]);
        $other = User::factory()->create(['role_id' => $roleId, 'email_verified_at' => now()]);

        $versionId = (int) SalesScriptVersion::query()->value('id');

        $this->actingAs($owner)
            ->post(route('scripts.sessions.store'), ['sales_script_version_id' => $versionId])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->first();
        $this->assertNotNull($session);

        $this->actingAs($other)
            ->post(route('scripts.sessions.advance', $session), ['sales_script_reaction_class_id' => null])
            ->assertForbidden();
    }
}
