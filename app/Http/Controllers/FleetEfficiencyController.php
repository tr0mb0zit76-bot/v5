<?php

namespace App\Http\Controllers;

use App\Services\FleetTripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class FleetEfficiencyController extends Controller
{
    public function __construct(
        private readonly FleetTripService $fleetTripService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);

        return Inertia::render('Fleet/Efficiency', [
            'summary' => $this->fleetTripService->efficiencySummary(),
        ]);
    }
}
