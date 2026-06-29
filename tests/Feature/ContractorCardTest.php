<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractorCardTest extends TestCase
{
    public function test_contractor_card_stores_signer_position_contact_position_decision_maker_and_postal_address(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post(route('contractors.store'), [
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'postal_address' => '443000, Самара, а/я 15',
            'signer_name_nominative' => 'Иванов Иван Иванович',
            'signer_name_prepositional' => 'Иванова Ивана Ивановича',
            'signer_position' => 'Генеральный директор',
            'signer_authority_basis' => 'Устава',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'contacts' => [
                [
                    'full_name' => 'Петров Петр',
                    'position' => 'Руководитель логистики',
                    'phone' => '+79990000000',
                    'email' => 'petrov@example.com',
                    'is_primary' => true,
                    'is_decision_maker' => true,
                    'notes' => 'Согласует ставки',
                ],
            ],
        ]);

        $contractorId = DB::table('contractors')->value('id');

        $response->assertRedirect(route('contractors.show', ['contractor' => $contractorId]));
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'postal_address' => '443000, Самара, а/я 15',
            'signer_position' => 'Генеральный директор',
        ]);
        $this->assertDatabaseHas('contractor_contacts', [
            'contractor_id' => $contractorId,
            'full_name' => 'Петров Петр',
            'position' => 'Руководитель логистики',
            'is_primary' => true,
            'is_decision_maker' => true,
        ]);
    }

    private function createAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['contractors']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        $user->role_id = $roleId;

        return $user;
    }
}
