<?php

namespace Tests\Unit\Services\Fleet;

use App\Models\FleetVehicle;
use App\Services\Fleet\FleetVehicleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FleetVehicleRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fleet_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_contractor_id');
            $table->string('tractor_brand', 120)->nullable();
            $table->string('trailer_brand', 120)->nullable();
            $table->string('tractor_plate', 32)->nullable();
            $table->string('trailer_plate', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_register_reuses_existing_vehicle_for_same_owner_and_tractor_plate(): void
    {
        DB::table('contractors')->insert([
            'id' => 98,
            'name' => 'ООО Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $registry = app(FleetVehicleRegistry::class);

        $first = $registry->register(98, [
            'tractor_brand' => 'Dongfeng',
            'tractor_plate' => 'с357хк797',
        ]);

        $second = $registry->register(98, [
            'tractor_brand' => 'Dongfeng',
            'tractor_plate' => 'С357ХК797',
            'notes' => 'повтор',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FleetVehicle::query()->where('owner_contractor_id', 98)->count());
        $this->assertSame('повтор', $second->fresh()->notes);
    }
}
