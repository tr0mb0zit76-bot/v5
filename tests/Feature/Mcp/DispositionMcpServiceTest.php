<?php

namespace Tests\Feature\Mcp;

use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\DispositionMcpService;
use App\Support\DispositionSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesInTransitOrders;
use Tests\TestCase;

class DispositionMcpServiceTest extends TestCase
{
    use CreatesInTransitOrders;

    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable: '.$exception->getMessage());
        }
    }

    public function test_upsert_disposition_entry_persists_location(): void
    {
        if (! Schema::hasTable('disposition_entries')) {
            $this->markTestSkipped('disposition_entries table is not migrated.');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Администратор', 'permissions' => [], 'visibility_areas' => ['orders']],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $order = $this->createInTransitOrder(['manager_id' => $user->id]);

        $date = Carbon::today()->toDateString();
        $service = app(DispositionMcpService::class);

        $result = $service->upsertEntry(
            $user,
            $order->id,
            $date,
            DispositionSlot::Morning->value,
            'Москва',
            null,
        );

        $this->assertSame('Москва', $result['entry']['location']);
        $this->assertDatabaseHas('disposition_entries', [
            'order_id' => $order->id,
            'date' => $date,
            'slot' => DispositionSlot::Morning->value,
            'location' => 'Москва',
            'recorded_by' => $user->id,
        ]);
    }
}
