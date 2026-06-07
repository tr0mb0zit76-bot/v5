<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\ContractorPortrait;
use App\Models\User;
use App\Services\Contractor\ContractorPortraitCoverage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorPortraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractor_interactions',
            'contractor_contacts',
            'contractor_portraits',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_portraits', function (Blueprint $table): void {
            $table->foreignId('contractor_id')->primary()->constrained('contractors')->cascadeOnDelete();
            $table->string('communication_style', 32)->default('unknown');
            $table->string('price_sensitivity', 32)->default('unknown');
            $table->string('preferred_channel', 32)->default('unknown');
            $table->string('decision_cadence', 32)->default('unknown');
            $table->string('relationship_trust', 32)->default('unknown');
            $table->text('success_criteria')->nullable();
            $table->json('typical_objections')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedTinyInteger('coverage_pct')->default(0);
            $table->timestamp('portrait_updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contractor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_decision_maker')->default(false);
            $table->string('role_in_deal', 32)->nullable();
            $table->text('communication_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('contractor_contact_id')->nullable()->constrained('contractor_contacts')->nullOnDelete();
            $table->timestamp('contacted_at');
            $table->string('channel', 50);
            $table->string('outcome_code', 32)->nullable();
            $table->timestamp('next_contact_at')->nullable();
            $table->string('subject')->nullable();
            $table->text('summary')->nullable();
            $table->string('result')->nullable();
            $table->json('objection_tags')->nullable();
            $table->boolean('merge_to_portrait')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function test_portrait_update_recalculates_coverage(): void
    {
        $user = $this->makeAdminUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Портрет',
            'owner_id' => $user->id,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Иванов',
            'role_in_deal' => 'decision_maker',
            'is_decision_maker' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('contractors.portrait.update', $contractor), [
            'communication_style' => 'analytical',
            'preferred_channel' => 'phone',
            'success_criteria' => 'Доставить в срок без срывов',
            'typical_objections' => ['price'],
        ]);

        $response->assertRedirect(route('contractors.show', ['contractor' => $contractor->id, 'tab' => 'portrait']));

        $portrait = ContractorPortrait::query()->findOrFail($contractor->id);
        $this->assertSame('analytical', $portrait->communication_style);
        $this->assertGreaterThanOrEqual(55, $portrait->coverage_pct);
    }

    public function test_interaction_merge_updates_portrait_objections(): void
    {
        $user = $this->makeAdminUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Контакт',
            'owner_id' => $user->id,
        ]);

        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Петров',
            'role_in_deal' => 'decision_maker',
        ]);

        $response = $this->actingAs($user)->postJson(route('contractors.portrait-interactions.store', $contractor), [
            'contractor_contact_id' => $contact->id,
            'contacted_at' => now()->toIso8601String(),
            'channel' => 'phone',
            'outcome_code' => 'objection',
            'summary' => 'Клиент считает ставку высокой',
            'objection_tags' => ['price'],
            'merge_to_portrait' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('portrait.typical_objections', ['price']);
        $this->assertGreaterThanOrEqual(
            ContractorPortraitCoverage::ASSISTANT_THRESHOLD,
            (int) $response->json('portrait.coverage_pct'),
        );
    }

    private function makeAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['contractors', 'leads', 'orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Admin',
            'email' => 'portrait-admin@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
