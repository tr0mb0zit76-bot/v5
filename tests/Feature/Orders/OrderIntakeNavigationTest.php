<?php

namespace Tests\Feature\Orders;

use App\Models\OrderIntakeDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderIntakeNavigationTest extends TestCase
{
    public function test_calculate_compensation_tolerates_unparseable_order_date(): void
    {
        $user = $this->createOrdersUser();

        $response = $this->actingAs($user)->postJson(route('orders.calculate-compensation'), [
            'customer_rate' => 100000,
            'carrier_rate' => 80000,
            'manager_id' => $user->id,
            'order_date' => 'завтра',
            'customer_payment_form' => 'vat',
        ]);

        $response->assertOk();
        $response->assertJsonPath('deal_type', 'unknown');
    }

    public function test_intake_draft_show_sanitizes_invalid_dates(): void
    {
        if (! Schema::hasTable('order_intake_drafts')) {
            $this->markTestSkipped('order_intake_drafts table not available.');
        }

        $user = $this->createOrdersUser();

        $draft = OrderIntakeDraft::query()->create([
            'user_id' => $user->id,
            'source_original_name' => 'test',
            'wizard_patch' => [
                'order_date' => 'завтра',
                'route_points' => [
                    ['type' => 'loading', 'planned_date' => '2026-06-03'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->getJson(route('orders.intake.draft', ['draft' => $draft->id]));

        $response->assertOk();
        $response->assertJsonPath('wizard_patch.order_date', null);
        $response->assertJsonPath('wizard_patch.route_points.0.planned_date', '2026-06-03');
        $response->assertJsonPath('wizard_path', '/orders/create?intake_draft='.$draft->id);

        $draft->delete();
    }

    private function createOrdersUser(): User
    {
        $roleId = DB::table('roles')->where('name', 'manager')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode(['orders'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        $user->role_id = $roleId;

        return $user;
    }
}
