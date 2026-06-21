<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorCardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractor_contacts',
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
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('postal_address')->nullable();
            $table->string('signer_name_nominative')->nullable();
            $table->string('signer_name_prepositional')->nullable();
            $table->string('signer_position')->nullable();
            $table->string('signer_authority_basis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_own_company')->default(false);
            $table->boolean('stop_on_limit')->default(false);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contractor_id');
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_decision_maker')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

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

        $response->assertRedirect(route('contractors.show', ['contractor' => $contractorId, 'type' => 'customer']));
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
