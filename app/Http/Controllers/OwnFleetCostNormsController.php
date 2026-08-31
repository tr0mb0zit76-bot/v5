<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnFleetCostNormsRequest;
use App\Services\OwnFleetCostNormsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OwnFleetCostNormsController extends Controller
{
    public function __construct(
        private readonly OwnFleetCostNormsService $normsService,
    ) {}

    public function edit(): Response
    {
        abort_unless(Schema::hasTable('own_fleet_cost_norms'), 404);

        return Inertia::render('Fleet/CostNorms', [
            'norms' => $this->normsService->pagePayload(),
        ]);
    }

    public function update(UpdateOwnFleetCostNormsRequest $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('own_fleet_cost_norms'), 404);

        $this->normsService->update($request->validated(), $request->user());

        return back()->with('success', 'Нормы себестоимости сохранены.');
    }
}
