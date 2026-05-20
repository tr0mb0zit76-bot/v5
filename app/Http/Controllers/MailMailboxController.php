<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCommercialMailRequest;
use App\Http\Requests\UpdateMailMessageImportanceRequest;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Services\CommercialMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailMailboxController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
    ) {}

    public function index(Request $request): Response
    {
        $threads = collect();

        if ($this->commercialMail->tablesReady()) {
            $threads = MailThread::query()
                ->with([
                    'lead:id,number,title',
                    'messages' => fn ($q) => $q->limit(1),
                ])
                ->orderByDesc('last_message_at')
                ->limit(100)
                ->get()
                ->map(fn (MailThread $thread): array => [
                    'id' => $thread->id,
                    'subject' => $thread->subject,
                    'lead_id' => $thread->lead_id,
                    'lead_number' => $thread->lead?->number,
                    'lead_title' => $thread->lead?->title,
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                    'last_outbound_at' => $thread->last_outbound_at?->toIso8601String(),
                    'preview' => $thread->messages->first()?->retention_summary
                        ?? $thread->messages->first()?->body_text,
                ]);
        }

        return Inertia::render('Mail/Index', [
            'threads' => $threads->values()->all(),
            'leads' => Lead::query()
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'number', 'title', 'counterparty_id'])
                ->map(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'number' => $lead->number,
                    'title' => $lead->title,
                    'counterparty_id' => $lead->counterparty_id,
                ])
                ->values()
                ->all(),
            'fromEmail' => (string) config('mail.from.address'),
        ]);
    }

    public function send(SendCommercialMailRequest $request): RedirectResponse
    {
        $lead = null;

        if ($request->filled('lead_id')) {
            $lead = Lead::query()->findOrFail((int) $request->input('lead_id'));
        }

        $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $request->user(),
            lead: $lead,
            offer: null,
            ccEmails: $request->input('cc', []),
        );

        return redirect()->route('mail.index')->with('flash', [
            'type' => 'success',
            'message' => 'Письмо отправлено.',
        ]);
    }

    public function updateImportance(UpdateMailMessageImportanceRequest $request, MailMessage $mailMessage): RedirectResponse
    {
        $mailMessage->update([
            'is_important' => $request->boolean('is_important'),
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => $request->boolean('is_important')
                ? 'Сообщение помечено как важное.'
                : 'Снята отметка «важно».',
        ]);
    }
}
