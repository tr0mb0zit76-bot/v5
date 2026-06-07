<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveContractorPrintFormChangeRequest;
use App\Http\Requests\SubmitContractorPrintFormChangeRequest;
use App\Http\Requests\UpdateContractorPrintFormBasicTermsRequest;
use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\PrintFormBasicTerm;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Http\RedirectResponse;

class ContractorPrintFormController extends Controller
{
    public function __construct(
        private readonly ContractorPrintFormChangeRequestService $changeRequests,
    ) {}

    public function updateBasicTerms(UpdateContractorPrintFormBasicTermsRequest $request, Contractor $contractor): RedirectResponse
    {
        abort_if($contractor->isOwnCompanyProfile(), 403);

        $user = $request->user();
        abort_if($user === null, 403);

        if (! $this->changeRequests->canDirectManagePrintForm($user)) {
            abort(403, 'Прямое сохранение доступно только администратору или настройкам системы. Отправьте на согласование.');
        }

        $validated = $request->validated();
        $party = (string) $validated['party'];
        $items = is_array($validated['items'] ?? null) ? $validated['items'] : [];

        $this->changeRequests->syncBasicTermsDirectly($contractor, $party, $items, $user);

        $partyLabel = $party === PrintFormBasicTerm::PARTY_CARRIER ? 'перевозчика' : 'заказчика';

        return redirect()
            ->route('contractors.show', [
                'contractor' => $contractor->id,
                'tab' => 'cooperation',
                'print_party' => $party,
            ])
            ->with('success', "Базовые условия {$partyLabel} сохранены для контрагента.");
    }

    public function submitChange(SubmitContractorPrintFormChangeRequest $request, Contractor $contractor): RedirectResponse
    {
        abort_if($contractor->isOwnCompanyProfile(), 403);

        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validated();
        $party = (string) $validated['party'];

        $this->changeRequests->submitBasicTermsChange(
            $contractor,
            $party,
            is_array($validated['items']) ? $validated['items'] : [],
            $user,
            $validated['manager_notes'] ?? null,
            $validated['yurik_summary'] ?? null,
        );

        return redirect()
            ->route('contractors.show', [
                'contractor' => $contractor->id,
                'tab' => 'cooperation',
                'print_party' => $party,
            ])
            ->with('success', 'Заявка на согласование базовых условий отправлена руководителю.');
    }

    public function resolveChange(
        ResolveContractorPrintFormChangeRequest $request,
        Contractor $contractor,
        ContractorPrintFormChangeRequest $printFormChange,
    ): RedirectResponse {
        abort_if((int) $printFormChange->contractor_id !== (int) $contractor->id, 404);

        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validated();
        $action = (string) $validated['action'];

        if ($action === 'approve') {
            $this->changeRequests->approve($printFormChange, $user);
            $message = 'Базовые условия утверждены и сохранены в карточке контрагента.';
        } elseif ($action === 'reject') {
            $this->changeRequests->reject($printFormChange, $user, (string) ($validated['reason'] ?? ''));
            $message = 'Заявка на изменение формы отклонена.';
        } else {
            $this->changeRequests->markNeedsCounterparty($printFormChange, $user, $validated['notes'] ?? null);
            $message = 'Заявка возвращена на согласование с контрагентом.';
        }

        return redirect()
            ->route('contractors.show', [
                'contractor' => $contractor->id,
                'tab' => 'cooperation',
                'print_party' => $printFormChange->party,
            ])
            ->with('success', $message);
    }
}
