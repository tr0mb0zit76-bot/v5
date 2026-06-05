<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCommercialMailRequest;
use App\Http\Requests\SendReplyMailRequest;
use App\Http\Requests\UpdateMailMessageImportanceRequest;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\User;
use App\Services\Commercial\MailMailboxAuthorization;
use App\Services\CommercialMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MailMailboxController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly MailMailboxAuthorization $mailboxAuth,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('Mail/Index', [
            'threads' => $this->loadThreadSummaries($user),
            'selectedThread' => null,
            'messages' => [],
            'leads' => $this->loadLeadOptions(),
            'orders' => $this->loadOrderOptions(),
            'fromEmail' => (string) ($user->email ?: config('mail.from.address')),
            'replyDefaults' => null,
        ]);
    }

    public function show(Request $request, MailThread $mailThread): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessThread($user, $mailThread), 403);

        $mailThread->load([
            'lead:id,number,title',
            'contractor:id,name',
            'order:id,order_number',
        ]);

        return Inertia::render('Mail/Index', [
            'threads' => $this->loadThreadSummaries($user),
            'selectedThread' => $this->serializeThread($mailThread, detailed: true),
            'messages' => $this->loadThreadMessages($mailThread),
            'leads' => $this->loadLeadOptions(),
            'orders' => $this->loadOrderOptions(),
            'fromEmail' => (string) ($user->email ?: config('mail.from.address')),
            'replyDefaults' => [
                'to' => $this->commercialMail->suggestReplyRecipients($mailThread, $user),
                'subject' => $mailThread->subject,
            ],
        ]);
    }

    public function send(SendCommercialMailRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $lead = null;

        if ($request->filled('lead_id')) {
            $lead = Lead::query()->findOrFail((int) $request->input('lead_id'));
        }

        $result = $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $user,
            lead: $lead,
            ccEmails: $request->input('cc', []),
            orderId: $request->filled('order_id') ? (int) $request->input('order_id') : null,
            contractorId: $lead?->counterparty_id,
        );

        return redirect()
            ->route('mail.threads.show', $result['thread'])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Письмо отправлено.',
            ]);
    }

    public function reply(SendReplyMailRequest $request, MailThread $mailThread): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessThread($user, $mailThread), 403);

        $this->commercialMail->replyInThread(
            thread: $mailThread,
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $user,
            ccEmails: $request->input('cc', []),
        );

        return redirect()
            ->route('mail.threads.show', $mailThread)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Ответ отправлен.',
            ]);
    }

    public function updateImportance(UpdateMailMessageImportanceRequest $request, MailMessage $mailMessage): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessMessage($user, $mailMessage), 403);

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

    /**
     * @return list<array<string, mixed>>
     */
    private function loadThreadSummaries(User $user): array
    {
        if (! $this->commercialMail->tablesReady()) {
            return [];
        }

        $query = MailThread::query()
            ->with([
                'messages' => fn ($q) => $q->orderByDesc('sent_at')->orderByDesc('id')->limit(1),
                'lead:id,number,title',
                'contractor:id,name',
                'order:id,order_number',
            ])
            ->orderByDesc('last_message_at')
            ->limit(100);

        $this->mailboxAuth->applyThreadScope($query, $user);

        return $query
            ->get()
            ->map(fn (MailThread $thread): array => $this->serializeThread($thread))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadThreadMessages(MailThread $thread): array
    {
        return $thread->messages()
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get()
            ->map(fn (MailMessage $message): array => $this->serializeMessage($message))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeThread(MailThread $thread, bool $detailed = false): array
    {
        $latest = $thread->relationLoaded('messages') ? $thread->messages->first() : null;

        $data = [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'lead_id' => $thread->lead_id,
            'lead_number' => $thread->lead?->number,
            'lead_title' => $thread->lead?->title,
            'order_id' => $thread->order_id,
            'order_number' => $thread->order?->order_number,
            'contractor_id' => $thread->contractor_id,
            'contractor_name' => $thread->contractor?->name,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
            'last_inbound_at' => $thread->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $thread->last_outbound_at?->toIso8601String(),
            'preview' => $latest !== null
                ? ($latest->retention_summary ?? Str::limit((string) ($latest->body_text ?? ''), 240))
                : null,
        ];

        if ($detailed) {
            $data['mailbox_user_id'] = $thread->mailbox_user_id;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(MailMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'from_email' => $message->from_email,
            'to_emails' => $message->to_emails ?? [],
            'cc_emails' => $message->cc_emails ?? [],
            'subject' => $message->subject,
            'body_text' => $message->bodyPurged()
                ? ($message->retention_summary ?? '(тело письма удалено по политике хранения)')
                : $message->body_text,
            'body_purged' => $message->bodyPurged(),
            'is_important' => (bool) $message->is_important,
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadLeadOptions(): array
    {
        return Lead::query()
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
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadOrderOptions(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return Order::query()
            ->orderByDesc('id')
            ->limit(150)
            ->get(['id', 'order_number'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
            ])
            ->values()
            ->all();
    }
}
