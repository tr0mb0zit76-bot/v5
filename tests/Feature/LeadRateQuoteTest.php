<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadRateQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadRateQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_store_rate_quote_on_lead(): void
    {
        if (! Schema::hasTable('lead_rate_quotes') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('lead_rate_quotes migration is not applied.');
        }

        $manager = $this->createManagerUser();
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post(route('leads.rate-quotes.store', $lead), [
                'carrier_name' => 'ООО Перевозчик',
                'rate' => 85000,
                'currency' => 'RUB',
                'payment_form' => 'bank_transfer',
                'source' => 'phone',
                'comment' => 'Готов завтра',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lead_rate_quotes', [
            'lead_id' => $lead->id,
            'carrier_name' => 'ООО Перевозчик',
            'rate' => 85000,
            'status' => LeadRateQuote::STATUS_RECEIVED,
            'source' => LeadRateQuote::SOURCE_PHONE,
        ]);
    }

    public function test_selecting_quote_updates_lead_carrier_cost_and_rejects_others(): void
    {
        if (! Schema::hasTable('lead_rate_quotes') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('lead_rate_quotes migration is not applied.');
        }

        $manager = $this->createManagerUser();
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'created_by' => $manager->id,
            'calculated_cost' => 100000,
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => null,
                    'contractor_name' => null,
                    'estimated_cost' => 100000,
                ],
            ],
        ]);

        $winner = LeadRateQuote::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'carrier_name' => 'Победитель',
            'rate' => 72000,
            'payment_form' => 'cash',
            'status' => LeadRateQuote::STATUS_RECEIVED,
        ]);

        $loser = LeadRateQuote::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'carrier_name' => 'Дорогой',
            'rate' => 91000,
            'status' => LeadRateQuote::STATUS_RECEIVED,
        ]);

        $this->actingAs($manager)
            ->post(route('leads.rate-quotes.select', [$lead, $winner]))
            ->assertRedirect();

        $lead->refresh();
        $winner->refresh();
        $loser->refresh();

        $this->assertSame(LeadRateQuote::STATUS_SELECTED, $winner->status);
        $this->assertSame(LeadRateQuote::STATUS_REJECTED, $loser->status);
        $this->assertEquals(72000.0, (float) $lead->calculated_cost);
        $this->assertSame('cash', $lead->carrier_payment_form);
        $this->assertSame('Победитель', data_get($lead->performers, '0.contractor_name'));
        $this->assertEquals(72000.0, (float) data_get($lead->performers, '0.estimated_cost'));
    }

    public function test_lead_show_includes_rate_quotes(): void
    {
        if (! Schema::hasTable('lead_rate_quotes') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('lead_rate_quotes migration is not applied.');
        }

        $manager = $this->createManagerUser();
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        LeadRateQuote::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'carrier_name' => 'В списке',
            'rate' => 55000,
        ]);

        $this->actingAs($manager)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('selectedLead.rate_quotes', 1)
                ->where('selectedLead.rate_quotes.0.carrier_label', 'В списке')
                ->where('selectedLead.rate_quotes.0.rate', '55000.00'));
    }

    private function createManagerUser(): User
    {
        $role = DB::table('roles')->where('name', 'manager')->first();

        if ($role === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode(['leads', 'orders']),
                'visibility_scopes' => json_encode(['leads' => 'own', 'orders' => 'own']),
                'columns_config' => json_encode([]),
                'permissions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = (int) $role->id;
            $areas = json_decode((string) $role->visibility_areas, true) ?: [];
            if (! in_array('leads', $areas, true)) {
                $areas[] = 'leads';
                DB::table('roles')->where('id', $roleId)->update([
                    'visibility_areas' => json_encode($areas),
                ]);
            }
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
