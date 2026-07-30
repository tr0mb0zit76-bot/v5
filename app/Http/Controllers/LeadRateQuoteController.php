<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRateQuoteRequest;
use App\Models\Lead;
use App\Models\LeadRateQuote;
use App\Services\Leads\LeadRateQuoteService;
use App\Support\LeadViewAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadRateQuoteController extends Controller
{
    public function __construct(
        private readonly LeadRateQuoteService $quotes,
    ) {}

    public function store(StoreLeadRateQuoteRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->quotes->tablesReady(), 404);
        abort_unless(LeadViewAuthorization::userCanViewLead($request->user(), $lead), 403);

        $this->quotes->store($lead, $request->validated(), $request->user());

        return back()->with('message', 'Котировка добавлена.');
    }

    public function select(Request $request, Lead $lead, LeadRateQuote $quote): RedirectResponse
    {
        abort_unless($this->quotes->tablesReady(), 404);
        abort_unless(LeadViewAuthorization::userCanViewLead($request->user(), $lead), 403);
        abort_unless($quote->lead_id === $lead->id, 404);

        $this->quotes->select($lead, $quote);

        return back()->with('message', 'Котировка выбрана: ставка перевозчика обновлена.');
    }
}
