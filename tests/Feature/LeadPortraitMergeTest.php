<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorPortrait;
use App\Models\Lead;
use App\Models\User;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadPortraitMergeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractor_interactions',
            'contractor_contacts',
            'contractor_portraits',
            'lead_offers',
            'lead_activities',
            'lead_cargo_items',
            'lead_route_points',
            'leads',
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

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title')->nullable();
            $table->json('lead_qualification')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_route_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_cargo_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function test_merge_from_lead_qualification_updates_portrait(): void
    {
        $user = $this->makeLeadsUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Лид',
            'owner_id' => $user->id,
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-1',
            'status' => 'new',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
            'title' => 'Тест',
            'lead_qualification' => [
                'need' => 'Доставка без срывов',
                'authority' => 'Иванов, директор',
                'budget' => 'Жмут по бюджету',
                'timeline' => 'Срочно до пятницы',
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('leads.portrait-merge', $lead), [
            'qualification' => $lead->lead_qualification,
        ]);

        $response->assertOk();
        $response->assertJsonPath('portrait.success_criteria', 'Доставка без срывов');
        $response->assertJsonPath('portrait.price_sensitivity', 'high');
        $response->assertJsonPath('portrait.decision_cadence', 'fast');

        $portrait = ContractorPortrait::query()->findOrFail($contractor->id);
        $this->assertStringContainsString('Иванов', (string) $portrait->internal_notes);
        $this->assertStringContainsString('Срочно до пятницы', (string) $portrait->internal_notes);
    }

    public function test_preview_skips_already_filled_success_criteria(): void
    {
        $user = $this->makeLeadsUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заполнено',
            'owner_id' => $user->id,
        ]);

        ContractorPortrait::query()->create([
            'contractor_id' => $contractor->id,
            'communication_style' => ContractorPortraitDictionary::UNKNOWN,
            'price_sensitivity' => ContractorPortraitDictionary::UNKNOWN,
            'preferred_channel' => ContractorPortraitDictionary::UNKNOWN,
            'decision_cadence' => ContractorPortraitDictionary::UNKNOWN,
            'relationship_trust' => ContractorPortraitDictionary::UNKNOWN,
            'success_criteria' => 'Уже известно',
            'coverage_pct' => 20,
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-2',
            'status' => 'new',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
            'lead_qualification' => ['need' => 'Новая потребность'],
        ]);

        $response = $this->actingAs($user)->getJson(route('leads.portrait-merge.preview', $lead));

        $response->assertOk();
        $response->assertJsonFragment(['Потребность — критерии успеха уже заполнены']);
        $this->assertSame([], $response->json('proposed'));
    }

    private function makeLeadsUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['leads', 'contractors']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Manager',
            'email' => 'manager-lead-portrait@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
