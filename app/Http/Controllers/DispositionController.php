<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertDispositionEntryRequest;
use App\Services\Disposition\DispositionGridService;
use App\Services\Disposition\DispositionKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DispositionController extends Controller
{
    public function __construct(
        private readonly DispositionGridService $grid,
        private readonly DispositionKpiService $kpi,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $payload = $this->grid->buildGridPayload($user);

        return Inertia::render('Disposition/Index', [
            'dates' => $payload['dates'],
            'today' => $payload['today'],
            'rows' => $payload['rows'],
            'status_filter' => $payload['status_filter'],
            'kpi' => $this->kpi->metricsForUser($user),
        ]);
    }

    public function upsert(UpsertDispositionEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->grid->upsertCell(
            $request->user(),
            (int) $data['order_id'],
            (string) $data['date'],
            (string) $data['slot'],
            array_key_exists('location', $data) ? (string) ($data['location'] ?? '') : null,
            array_key_exists('comment', $data) ? (string) ($data['comment'] ?? '') : null,
        );

        return response()->json($result);
    }
}
