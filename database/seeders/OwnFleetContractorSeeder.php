<?php

namespace Database\Seeders;

use App\Services\OwnFleetContractorService;
use Illuminate\Database\Seeder;

class OwnFleetContractorSeeder extends Seeder
{
    public function run(): void
    {
        app(OwnFleetContractorService::class)->ensureContractor();
    }
}
