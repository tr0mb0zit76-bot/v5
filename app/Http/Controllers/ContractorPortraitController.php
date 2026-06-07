<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractorInteractionRequest;
use App\Http\Requests\UpdateContractorPortraitRequest;
use App\Models\Contractor;
use App\Services\Contractor\ContractorPortraitService;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ContractorPortraitController extends Controller
{
    public function __construct(
        private readonly ContractorPortraitService $portraitService,
    ) {}

    public function update(UpdateContractorPortraitRequest $request, Contractor $contractor): RedirectResponse
    {
        $this->portraitService->updatePortrait(
            $contractor,
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('contractors.show', ['contractor' => $contractor->id, 'tab' => 'portrait'])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Портрет контрагента сохранён.',
            ]);
    }

    public function storeInteraction(StoreContractorInteractionRequest $request, Contractor $contractor): JsonResponse
    {
        $validated = $request->validated();

        if (filled($validated['contractor_contact_id'] ?? null)) {
            abort_unless(
                $contractor->contacts()->whereKey((int) $validated['contractor_contact_id'])->exists(),
                422,
                'Контакт не принадлежит этому контрагенту.',
            );
        }

        $interaction = $this->portraitService->storeInteraction(
            $contractor,
            $validated,
            $request->user(),
        );

        $portrait = $this->portraitService->serializePortrait($contractor->fresh('portrait')?->portrait, $contractor);

        return response()->json([
            'interaction' => [
                'id' => $interaction->id,
                'contacted_at' => optional($interaction->contacted_at)?->toIso8601String(),
                'channel' => $interaction->channel,
                'outcome_code' => $interaction->outcome_code,
                'outcome_label' => ContractorPortraitDictionary::label('outcome_code', $interaction->outcome_code),
                'next_contact_at' => optional($interaction->next_contact_at)?->toIso8601String(),
                'subject' => $interaction->subject,
                'summary' => $interaction->summary,
                'result' => $interaction->result,
                'objection_tags' => is_array($interaction->objection_tags) ? $interaction->objection_tags : [],
                'contractor_contact_id' => $interaction->contractor_contact_id,
                'contact_name' => $interaction->contact?->full_name,
                'author_name' => $interaction->author?->name,
            ],
            'portrait' => $portrait,
        ]);
    }
}
