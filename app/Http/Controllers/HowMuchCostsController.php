<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateHowMuchCostsRequest;
use App\Services\HowMuchCostsCalculatorService;
use App\Services\OwnFleetCostNormsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HowMuchCostsController extends Controller
{
    public function __construct(
        private readonly HowMuchCostsCalculatorService $calculator,
        private readonly OwnFleetCostNormsService $normsService,
    ) {}

    public function index(): Response
    {
        $norms = Schema::hasTable('own_fleet_cost_norms')
            ? $this->normsService->pagePayload()
            : null;

        return Inertia::render('Modules/HowMuchCosts', [
            'norms' => $norms,
            'normsConfigured' => $norms !== null,
        ]);
    }

    public function calculate(CalculateHowMuchCostsRequest $request): JsonResponse
    {
        abort_unless(Schema::hasTable('own_fleet_cost_norms'), 404);

        return response()->json(
            $this->calculator->calculate($request->validated()),
        );
    }
}
