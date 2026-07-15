<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorPortrait;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ContractorReconciliationService;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractorAuthorizationTest extends TestCase
{
    public function test_department_scope_allows_department_contractor_and_denies_foreign_contractor_show(): void
    {
        $viewer = $this->makeScopedUser('department');
        $colleague = User::factory()->create(['role_id' => $viewer->role_id]);
        $outsider = User::factory()->create(['role_id' => $viewer->role_id]);
        $this->placeInDepartment([$viewer, $colleague]);

        $allowed = $this->makeCustomer($colleague, 'Разрешённый клиент');
        $foreign = $this->makeCustomer($outsider, 'Чужой клиент');
        $allowedOrder = Order::factory()->create([
            'customer_id' => $allowed->id,
            'manager_id' => $colleague->id,
        ]);
        $foreignOrder = Order::factory()->create([
            'customer_id' => $allowed->id,
            'manager_id' => $outsider->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('contractors.show', $allowed))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'selectedContractor.orders',
                    fn ($orders) => collect($orders)->pluck('id')->contains($allowedOrder->id)
                        && ! collect($orders)->pluck('id')->contains($foreignOrder->id),
                )
            );

        $this->actingAs($viewer)
            ->get(route('contractors.show', $foreign))
            ->assertForbidden();
    }

    public function test_own_scope_denies_foreign_update_and_destroy_but_allows_own_mutations(): void
    {
        $viewer = $this->makeScopedUser();
        $outsider = User::factory()->create(['role_id' => $viewer->role_id]);
        $own = $this->makeCustomer($viewer, 'Свой клиент');
        $ownToDelete = $this->makeCustomer($viewer, 'Свой клиент на удаление');
        $foreign = $this->makeCustomer($outsider, 'Чужой клиент');

        $this->actingAs($viewer)
            ->patch(route('contractors.update', $foreign), $this->updatePayload('Взломанное название'))
            ->assertForbidden();
        $this->assertSame('Чужой клиент', $foreign->fresh()->name);

        $this->actingAs($viewer)
            ->delete(route('contractors.destroy', $foreign))
            ->assertForbidden();
        $this->assertModelExists($foreign);

        $this->actingAs($viewer)
            ->patch(route('contractors.update', $own), $this->updatePayload('Обновлённый свой клиент'))
            ->assertRedirect(route('contractors.show', $own));
        $this->assertSame('Обновлённый свой клиент', $own->fresh()->name);

        $this->actingAs($viewer)
            ->delete(route('contractors.destroy', $ownToDelete))
            ->assertRedirect(route('contractors.index'));
        $this->assertModelMissing($ownToDelete);
    }

    public function test_own_scope_denies_foreign_portrait_update_and_allows_own_portrait_update(): void
    {
        $viewer = $this->makeScopedUser();
        $outsider = User::factory()->create(['role_id' => $viewer->role_id]);
        $own = $this->makeCustomer($viewer, 'Свой портрет');
        $foreign = $this->makeCustomer($outsider, 'Чужой портрет');
        $payload = ['communication_style' => 'analytical'];

        $this->actingAs($viewer)
            ->patch(route('contractors.portrait.update', $foreign), $payload)
            ->assertForbidden();
        $this->assertDatabaseMissing('contractor_portraits', ['contractor_id' => $foreign->id]);

        $this->actingAs($viewer)
            ->patch(route('contractors.portrait.update', $own), $payload)
            ->assertRedirect(route('contractors.show', ['contractor' => $own->id, 'tab' => 'portrait']));

        $this->assertSame(
            'analytical',
            ContractorPortrait::query()->findOrFail($own->id)->communication_style,
        );
    }

    public function test_own_scope_denies_foreign_print_form_update_and_allows_own_print_form_update(): void
    {
        $viewer = $this->makeScopedUser();
        $outsider = User::factory()->create(['role_id' => $viewer->role_id]);
        $own = $this->makeCustomer($viewer, 'Своя печатная форма');
        $foreign = $this->makeCustomer($outsider, 'Чужая печатная форма');
        $payload = [
            'party' => 'customer',
            'items' => ['Разрешённое условие'],
        ];

        $this->actingAs($viewer)
            ->put(route('contractors.print-form.basic-terms.update', $foreign), $payload)
            ->assertForbidden();
        $this->assertDatabaseMissing('print_form_basic_terms', [
            'contractor_id' => $foreign->id,
            'body' => 'Разрешённое условие',
        ]);

        $this->actingAs($viewer)
            ->put(route('contractors.print-form.basic-terms.update', $own), $payload)
            ->assertRedirect(route('contractors.show', [
                'contractor' => $own->id,
                'tab' => 'cooperation',
                'print_party' => 'customer',
            ]));

        $this->assertDatabaseHas('print_form_basic_terms', [
            'contractor_id' => $own->id,
            'party' => 'customer',
            'body' => 'Разрешённое условие',
        ]);
    }

    public function test_mobile_summary_and_finance_options_use_canonical_contractor_scope(): void
    {
        $viewer = $this->makeScopedUser();
        $outsider = User::factory()->create(['role_id' => $viewer->role_id]);
        $own = $this->makeCustomer($viewer, 'Свой мобильный клиент');
        $foreign = $this->makeCustomer($outsider, 'Чужой финансовый клиент');

        $this->actingAs($viewer)
            ->getJson(route('mobile.shell.contractors.summary', $own))
            ->assertOk()
            ->assertJsonPath('contractor.id', $own->id);

        $this->actingAs($viewer)
            ->getJson(route('mobile.shell.contractors.summary', $foreign))
            ->assertForbidden();

        $optionIds = app(ContractorReconciliationService::class)
            ->contractorOptions($viewer)
            ->pluck('id')
            ->all();

        $this->assertContains($own->id, $optionIds);
        $this->assertNotContains($foreign->id, $optionIds);
    }

    private function makeScopedUser(string $scope = 'own'): User
    {
        $role = Role::query()->create([
            'name' => 'contractor_auth_'.uniqid(),
            'display_name' => 'Contractor authorization',
            'permissions' => [],
            'visibility_areas' => ['contractors', 'orders', 'settings_system'],
            'visibility_scopes' => [
                'contractors' => $scope,
                'orders' => $scope,
            ],
        ]);

        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        RoleAccess::syncUserRoles($user, [$role->id]);

        return $user;
    }

    /**
     * @param  list<User>  $users
     */
    private function placeInDepartment(array $users): void
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Contractor auth '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($users as $user) {
            DB::table('department_user')->insert([
                'department_id' => $departmentId,
                'user_id' => $user->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function makeCustomer(User $owner, string $name): Contractor
    {
        return Contractor::query()->create([
            'type' => 'customer',
            'name' => $name,
            'owner_id' => $owner->id,
            'is_active' => true,
            'is_own_company' => false,
            'stop_on_limit' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(string $name): array
    {
        return [
            'type' => 'customer',
            'name' => $name,
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
        ];
    }
}
