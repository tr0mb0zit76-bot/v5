<?php

declare(strict_types=1);

namespace Tests\Feature\Epd;

use App\Models\OneCEpdRegistryEntry;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EpdRegistryTest extends TestCase
{
    public function test_clerk_can_open_epd_index(): void
    {
        if (! Schema::hasTable('one_c_epd_registry_entries')) {
            $this->markTestSkipped('Таблица one_c_epd_registry_entries недоступна.');
        }

        $clerk = $this->makeClerkUser();

        $this->actingAs($clerk)
            ->get(route('epd.index'))
            ->assertOk();
    }

    public function test_link_and_unlink_expedition_receipt_mirrors_order_one_c_document(): void
    {
        if (! Schema::hasTable('one_c_epd_registry_entries') || ! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('Нужны таблицы ЭПД registry и order_one_c_documents.');
        }

        $clerk = $this->makeClerkUser();
        $order = Order::factory()->create([
            'manager_id' => $clerk->id,
            'order_number' => 'АС-EPD-1',
        ]);

        $entry = OneCEpdRegistryEntry::query()->create([
            'publication_code' => 'autalliance',
            'document_ref' => (string) Str::uuid(),
            'document_ref_type' => 'UnavailableEntities.UnavailableEntity_075950fc-13b5-4027-9ef9-9d08696a2960',
            'document_type' => OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT,
            'document_type_label' => 'Экспедиторская расписка',
            'epd_number' => '00000000001',
            'epd_date' => '2026-09-07',
            'current_step' => 'Оформление',
            'current_step_done' => true,
            'shipper_name' => 'НОВАФАРМ ООО',
            'shipper_inn' => '5835139805',
            'last_synced_at' => now(),
        ]);

        $this->actingAs($clerk)
            ->postJson(route('epd.link', $entry), ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('entry.order_id', $order->id);

        $this->assertDatabaseHas('one_c_epd_registry_entries', [
            'id' => $entry->id,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('order_one_c_documents', [
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            'external_ref' => $entry->document_ref,
            'status' => OrderOneCDocument::STATUS_CREATED,
        ]);

        $this->actingAs($clerk)
            ->postJson(route('epd.unlink', $entry))
            ->assertOk()
            ->assertJsonPath('entry.order_id', null);

        $this->assertDatabaseMissing('order_one_c_documents', [
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            'external_ref' => $entry->document_ref,
        ]);
    }

    public function test_manager_cannot_link_foreign_order(): void
    {
        if (! Schema::hasTable('one_c_epd_registry_entries')) {
            $this->markTestSkipped('Таблица one_c_epd_registry_entries недоступна.');
        }

        $owner = $this->makeOrdersManager('epd_owner');
        $intruder = $this->makeOrdersManager('epd_intruder');
        $order = Order::factory()->create([
            'manager_id' => $owner->id,
        ]);

        $entry = OneCEpdRegistryEntry::query()->create([
            'publication_code' => 'autalliance',
            'document_ref' => (string) Str::uuid(),
            'document_type' => OneCEpdRegistryEntry::TYPE_EXPEDITION_ORDER,
            'document_type_label' => 'Поручение экспедитору',
            'epd_number' => '11',
            'last_synced_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->postJson(route('epd.link', $entry), ['order_id' => $order->id])
            ->assertStatus(422);
    }

    private function makeClerkUser(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'clerk',
        ], [
            'display_name' => 'Делопроизводитель',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders', 'documents', 'dashboard'],
            'visibility_scopes' => ['orders' => 'all', 'documents' => 'all'],
        ]);

        $role->update([
            'visibility_areas' => ['orders', 'documents', 'dashboard'],
            'visibility_scopes' => ['orders' => 'all', 'documents' => 'all'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function makeOrdersManager(string $roleName): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
        ], [
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders', 'documents', 'dashboard'],
            'visibility_scopes' => ['orders' => 'own', 'documents' => 'own'],
        ]);

        $role->update([
            'visibility_areas' => ['orders', 'documents', 'dashboard'],
            'visibility_scopes' => ['orders' => 'own', 'documents' => 'own'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
