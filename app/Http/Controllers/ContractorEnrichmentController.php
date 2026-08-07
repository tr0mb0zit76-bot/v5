<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\ContractorEnrichmentRun;
use App\Services\Contractor\ContractorEnrichmentService;
use App\Services\Contractor\ContractorInsightDraftService;
use App\Support\ContractorPortraitAuthorization;
use App\Support\ContractorViewAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ContractorEnrichmentController extends Controller
{
    public function __construct(
        private readonly ContractorEnrichmentService $enrichment,
        private readonly ContractorInsightDraftService $insightDrafts,
    ) {}

    public function show(Request $request, Contractor $contractor): JsonResponse
    {
        ContractorViewAuthorization::authorize($request->user(), $contractor);
        abort_unless(Schema::hasTable('contractor_enrichment_runs'), 422, 'Модуль обогащения недоступен.');

        return response()->json([
            'summary' => $this->enrichment->latestSummary($contractor),
            'dossier' => $this->enrichment->latestDossier($contractor),
            'pending_drafts' => Schema::hasTable('contractor_insight_drafts')
                ? $this->insightDrafts->serializePendingForContractor($contractor)->values()->all()
                : [],
            'can_manage' => ContractorPortraitAuthorization::canManage($request->user(), $contractor),
        ]);
    }

    public function store(Request $request, Contractor $contractor): JsonResponse
    {
        ContractorViewAuthorization::authorize($request->user(), $contractor);
        ContractorPortraitAuthorization::authorizeManage($request->user(), $contractor);
        abort_unless(Schema::hasTable('contractor_enrichment_runs'), 422, 'Модуль обогащения недоступен.');

        $validated = $request->validate([
            'force' => ['sometimes', 'boolean'],
            'async' => ['sometimes', 'boolean'],
        ]);

        $force = (bool) ($validated['force'] ?? false);
        $async = (bool) ($validated['async'] ?? true);

        if ($async) {
            $run = $this->enrichment->dispatch(
                $contractor,
                $request->user(),
                ContractorEnrichmentRun::TRIGGER_MANUAL,
                $force,
            );

            return response()->json([
                'queued' => true,
                'run' => [
                    'id' => $run->id,
                    'status' => $run->status,
                ],
                'summary' => $this->enrichment->latestSummary($contractor),
                'pending_drafts' => $this->insightDrafts->serializePendingForContractor($contractor)->values()->all(),
                'can_manage' => true,
            ]);
        }

        $result = $this->enrichment->runNow(
            $contractor,
            $request->user(),
            ContractorEnrichmentRun::TRIGGER_MANUAL,
            $force,
        );

        return response()->json([
            'queued' => false,
            'run' => [
                'id' => $result['run']->id,
                'status' => $result['run']->status,
            ],
            'drafts_created' => $result['drafts_created'],
            'summary' => $this->enrichment->latestSummary($contractor),
            'dossier' => $this->enrichment->latestDossier($contractor),
            'pending_drafts' => $this->insightDrafts->serializePendingForContractor($contractor)->values()->all(),
            'can_manage' => true,
        ]);
    }
}
