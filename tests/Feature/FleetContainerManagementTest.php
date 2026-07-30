<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\FleetContainer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetContainerManagementTest extends TestCase
{
    public function test_can_create_update_container_and_upload_document(): void
    {
        if (! Schema::hasTable('fleet_containers') || ! Schema::hasTable('fleet_container_documents')) {
            $this->markTestSkipped('fleet_containers migration is not applied.');
        }

        Storage::fake('public');

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'fleet_clerk',
            'visibility_areas' => json_encode(['dashboard', 'drivers'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $owner = Contractor::query()->create([
            'name' => 'ООО Контейнерпарк',
            'type' => 'carrier',
        ]);

        $create = $this->actingAs($user)->post(route('fleet.containers.store'), [
            'owner_contractor_id' => $owner->id,
            'container_number' => 'mscu 1234567',
            'size_code' => '40HC',
            'container_type' => 'dry',
            'notes' => 'Склад А',
        ]);

        $create->assertRedirect();
        $container = FleetContainer::query()->first();
        $this->assertNotNull($container);
        $this->assertSame('MSCU1234567', $container->container_number);
        $this->assertSame('40HC', $container->size_code);

        $this->actingAs($user)->patch(route('fleet.containers.update', $container), [
            'owner_contractor_id' => $owner->id,
            'container_number' => 'MSCU1234567',
            'size_code' => '40',
            'container_type' => 'reefer',
            'notes' => 'Обновлено',
        ])->assertRedirect(route('fleet.containers.show', $container));

        $container->refresh();
        $this->assertSame('40', $container->size_code);
        $this->assertSame('reefer', $container->container_type);

        $this->actingAs($user)->post(route('fleet.containers.documents.store', $container), [
            'document_type' => 'csc_plate',
            'file' => UploadedFile::fake()->create('csc.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('fleet.containers.show', $container));

        $this->assertSame(1, $container->documents()->count());

        $this->actingAs($user)
            ->get(route('fleet.containers.show', $container))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Fleet/Containers')
                ->where('selectedContainer.container_number', 'MSCU1234567')
                ->has('selectedContainer.documents', 1));
    }

    public function test_duplicate_number_for_same_owner_is_rejected(): void
    {
        if (! Schema::hasTable('fleet_containers')) {
            $this->markTestSkipped('fleet_containers migration is not applied.');
        }

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'fleet_clerk',
            'visibility_areas' => json_encode(['dashboard', 'drivers'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);
        $owner = Contractor::query()->create([
            'name' => 'ООО Парк',
            'type' => 'carrier',
        ]);

        FleetContainer::query()->create([
            'owner_contractor_id' => $owner->id,
            'container_number' => 'ABCD1234567',
            'size_code' => '20',
        ]);

        $this->actingAs($user)->post(route('fleet.containers.store'), [
            'owner_contractor_id' => $owner->id,
            'container_number' => 'ABCD1234567',
            'size_code' => '20',
        ])->assertSessionHasErrors('container_number');
    }
}
