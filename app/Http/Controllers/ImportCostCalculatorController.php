<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateImportCostRequest;
use App\Http\Requests\SearchImportCostTnVedRequest;
use App\Services\ImportCostCalculatorService;
use App\Support\ImportCostTnVedCatalog;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportCostCalculatorController extends Controller
{
    public function __construct(
        private readonly ImportCostCalculatorService $calculator,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Modules/ImportCostCalculator', $this->calculator->pagePayload());
    }

    public function calculate(CalculateImportCostRequest $request): JsonResponse
    {
        return response()->json(
            $this->calculator->calculate($request->validated()),
        );
    }

    public function searchTnVed(SearchImportCostTnVedRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'items' => ImportCostTnVedCatalog::search(
                (string) $validated['q'],
                (int) ($validated['limit'] ?? 30),
            ),
        ]);
    }
}
