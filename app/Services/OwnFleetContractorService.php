<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contractor;
use App\Support\OwnFleetCatalog;
use Illuminate\Support\Facades\Schema;

class OwnFleetContractorService
{
    public function ensureContractor(): ?Contractor
    {
        if (! Schema::hasTable('contractors')) {
            return null;
        }

        $attributes = [];

        if (Schema::hasColumn('contractors', 'type')) {
            $attributes['type'] = 'carrier';
        }

        if (Schema::hasColumn('contractors', 'is_active')) {
            $attributes['is_active'] = true;
        }

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $attributes['is_own_company'] = false;
        }

        if (Schema::hasColumn('contractors', 'is_verified')) {
            $attributes['is_verified'] = true;
        }

        return Contractor::query()->updateOrCreate(
            ['name' => OwnFleetCatalog::CONTRACTOR_NAME],
            $attributes,
        );
    }

    public function contractorId(): ?int
    {
        $contractor = $this->ensureContractor();

        return $contractor?->id;
    }
}
