<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCommercialMailRequest;
use App\Http\Requests\SendReplyMailRequest;
use App\Http\Requests\UpdateMailMessageImportanceRequest;
use App\Http\Requests\UpdateMailThreadLinksRequest;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\User;
use App\Services\Commercial\MailAttachmentPreviewService;
use App\Services\Commercial\MailMailboxAuthorization;
use App\Services\Commercial\MailThreadDeletionService;
use App\Services\Commercial\MailThreadLinkService;
use App\Services\Commercial\OrderMailContextService;
use App\Services\CommercialMailService;
use App\Services\DocumentStorageService;
use App\Support\MailSync\MailMailboxOwnerCatalog;
use App\Support\MailSync\MailMessageBodyPresenter;
use App\Support\MailSync\MailOutboundAttachmentRules;
use App\Support\VisibleOrderScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MailMailboxController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly MailMailboxAuthorization $mailboxAuth,
        private readonly OrderMailContextService $orderMailContext,
        private readonly MailMailboxOwnerCatalog $mailboxOwnerCatalog,
        private readonly MailThreadLinkService $threadLinks,
        private readonly MailThreadDeletionService $threadDeletion,
        private readonly MailAttachmentPreviewService $attachmentPreview,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $composeDefaults = $request->filled('order_id')
            ? $this->orderMailContext->composeDefaultsForOrderId((int) $request->input('order_id'))
            : null;

        $mailView = $this->mailboxOwnerCatalog->viewContext($user, $request);

        return Inertia::render('Mail/Index', [
            'threads' => $this->loadThreadSummaries($user, $mailView['selected_mailbox_user_id']),
            'mailView' => $mailView,
            'selectedThread' => null,
            'messages' => [],
            'leads' => $this->loadLeadOptions(),
            'orders' => $this->loadOrderOptions(),
            'fromEmail' => (string) ($user->email ?: config('mail.from.address')),
            'replyDefaults' => null,
            'composeDefaults' => $composeDefaults,
            'attachmentLimits' => $this->attachmentLimitsPayload(),
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
            'mailboxUser:id,name,email',
        ]);

        $mailView = $this->mailboxOwnerCatalog->viewContext($user, $request);

        return Inertia::render('Mail/Index', [
            'threads' => $this->loadThreadSummaries($user, $mailView['selected_mailbox_user_id']),
            'mailView' => $mailView,
            'selectedThread' => $this->serializeThread($mailThread, detailed: true, viewer: $user),
            'messages' => $this->loadThreadMessages($mailThread),
            'leads' => $this->loadLeadOptions(),
            'orders' => $this->loadOrderOptions(),
            'fromEmail' => (string) ($user->email ?: config('mail.from.address')),
            'replyDefaults' => [
                'to' => $this->commercialMail->suggestReplyRecipients($mailThread, $user),
                'subject' => $mailThread->subject,
            ],
            'composeDefaults' => null,
            'attachmentLimits' => $this->attachmentLimitsPayload(),
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

        $orderId = $request->filled('order_id') ? (int) $request->input('order_id') : null;
        $contractorId = $lead?->counterparty_id;

        if ($contractorId === null && $orderId !== null) {
            $contractorId = Order::query()->whereKey($orderId)->value('customer_id');
            $contractorId = $contractorId !== null ? (int) $contractorId : null;
        }

        $attachments = $this->commercialMail->storeUploadedAttachments(
            $request->file('attachments', []),
            $user,
            $orderId,
        );

        $result = $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $user,
            lead: $lead,
            ccEmails: $request->input('cc', []),
            attachments: $attachments,
            orderId: $orderId,
            contractorId: $contractorId,
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

        $attachments = $this->commercialMail->storeUploadedAttachments(
            $request->file('attachments', []),
            $user,
            $mailThread->order_id,
        );

        $this->commercialMail->replyInThread(
            thread: $mailThread,
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $user,
            ccEmails: $request->input('cc', []),
            attachments: $attachments,
        );

        return redirect()
            ->route('mail.threads.show', $mailThread)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Ответ отправлен.',
            ]);
    }

    public function downloadAttachment(Request $request, MailMessage $mailMessage, int $attachmentIndex): HttpResponse
    {
        $resolved = $this->resolveAttachment($request, $mailMessage, $attachmentIndex);

        return response($resolved['contents'], 200, [
            'Content-Type' => $resolved['mime'] !== '' ? $resolved['mime'] : 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $resolved['filename']).'"',
        ]);
    }

    public function previewAttachment(Request $request, MailMessage $mailMessage, int $attachmentIndex): HttpResponse
    {
        $resolved = $this->resolveAttachment($request, $mailMessage, $attachmentIndex);

        return $this->attachmentPreview->buildPreviewResponse(
            $resolved['contents'],
            $resolved['filename'],
            $resolved['mime'] !== '' ? $resolved['mime'] : null,
        );
    }

    /**
     * @return array{contents: string, filename: string, mime: string}
     */
    private function resolveAttachment(Request $request, MailMessage $mailMessage, int $attachmentIndex): array
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessMessage($user, $mailMessage), 403);

        $attachments = $mailMessage->attachments;

        if (! is_array($attachments)) {
            abort(404);
        }

        $attachment = $attachments[$attachmentIndex] ?? null;

        if (! is_array($attachment)) {
            abort(404);
        }

        $path = trim((string) ($attachment['file_path'] ?? ''));

        if ($path === '') {
            abort(404);
        }

        $filename = trim((string) ($attachment['original_name'] ?? $attachment['name'] ?? 'attachment'));
        $mime = trim((string) ($attachment['mime_type'] ?? 'application/octet-stream'));
        $driver = isset($attachment['storage_driver']) ? (string) $attachment['storage_driver'] : null;
        $contents = app(DocumentStorageService::class)->get($path, $driver);

        return [
            'contents' => $contents,
            'filename' => $filename,
            'mime' => $mime,
        ];
    }

    public function destroy(Request $request, MailThread $mailThread): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessThread($user, $mailThread), 403);

        $this->threadDeletion->delete($mailThread);

        return redirect()
            ->route('mail.index', $this->mailboxRedirectParams($request))
            ->with('flash', [
                'type' => 'success',
                'message' => 'Цепочка писем удалена из CRM.',
            ]);
    }

    public function updateLinks(UpdateMailThreadLinksRequest $request, MailThread $mailThread): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->mailboxAuth->canAccessThread($user, $mailThread), 403);

        $leadId = $request->filled('lead_id') ? (int) $request->input('lead_id') : null;
        $orderId = $request->filled('order_id') ? (int) $request->input('order_id') : null;

        $this->threadLinks->apply($mailThread, $leadId, $orderId);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Привязка цепочки обновлена.',
        ]);
    }

    public function linkOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $type = $request->string('type')->toString();
        $query = trim($request->string('q')->toString());

        if ($type === 'lead') {
            $builder = Lead::query()->orderByDesc('id')->limit(25);

            if ($query !== '') {
                $needle = '%'.$query.'%';

                if (ctype_digit($query)) {
                    $builder->where(function ($scoped) use ($query, $needle): void {
                        $scoped->whereKey((int) $query)
                            ->orWhere('number', 'like', $needle)
                            ->orWhere('title', 'like', $needle);
                    });
                } else {
                    $builder->where(function ($scoped) use ($needle): void {
                        $scoped->where('number', 'like', $needle)
                            ->orWhere('title', 'like', $needle);
                    });
                }
            }

            return response()->json([
                'items' => $builder
                    ->get(['id', 'number', 'title', 'counterparty_id'])
                    ->map(fn (Lead $lead): array => [
                        'id' => $lead->id,
                        'number' => $lead->number,
                        'title' => $lead->title,
                        'counterparty_id' => $lead->counterparty_id,
                        'label' => trim($lead->number.' — '.$lead->title),
                    ])
                    ->values()
                    ->all(),
            ]);
        }

        if ($type === 'order') {
            if (! Schema::hasTable('orders')) {
                return response()->json(['items' => []]);
            }

            $builder = VisibleOrderScope::apply(Order::query())->orderByDesc('id')->limit(25);

            if ($query !== '') {
                if (ctype_digit($query)) {
                    $builder->where(function ($scoped) use ($query): void {
                        $scoped->whereKey((int) $query)
                            ->orWhere('order_number', 'like', '%'.$query.'%');
                    });
                } else {
                    $builder->where('order_number', 'like', '%'.$query.'%');
                }
            }

            return response()->json([
                'items' => $builder
                    ->get(['id', 'order_number'])
                    ->map(fn (Order $order): array => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'label' => (string) ($order->order_number ?: '#'.$order->id),
                    ])
                    ->values()
                    ->all(),
            ]);
        }

        abort(422, 'Укажите type=lead или type=order.');
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
    private function loadThreadSummaries(User $user, ?int $mailboxUserId = null): array
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
                'mailboxUser:id,name,email',
            ])
            ->orderByDesc('last_message_at')
            ->limit(100);

        $this->mailboxAuth->applyThreadScope($query, $user);
        $this->mailboxOwnerCatalog->applyMailboxFilterToQuery($query, $user, $mailboxUserId);

        return $query
            ->get()
            ->map(fn (MailThread $thread): array => $this->serializeThread($thread, viewer: $user))
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
    private function serializeThread(MailThread $thread, bool $detailed = false, ?User $viewer = null): array
    {
        $latest = $thread->relationLoaded('messages') ? $thread->messages->first() : null;
        $includeMailboxMeta = $viewer !== null && $this->mailboxAuth->canViewAllMailboxes($viewer);

        $visibleOrderId = $this->visibleOrderId($thread);

        $data = [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'lead_id' => $thread->lead_id,
            'lead_number' => $thread->lead?->number,
            'lead_title' => $thread->lead?->title,
            'order_id' => $visibleOrderId,
            'order_number' => $visibleOrderId !== null ? $thread->order?->order_number : null,
            'contractor_id' => $thread->contractor_id,
            'contractor_name' => $thread->contractor?->name,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
            'last_inbound_at' => $thread->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $thread->last_outbound_at?->toIso8601String(),
            'preview' => $latest !== null
                ? MailMessageBodyPresenter::preview($latest)
                : null,
        ];

        if ($includeMailboxMeta) {
            $data['mailbox_user_id'] = $thread->mailbox_user_id;
            $data['mailbox_owner_label'] = $thread->mailboxUser !== null
                ? MailMailboxOwnerCatalog::shortLabel($thread->mailboxUser)
                : null;
            $data['mailbox_owner_name'] = $thread->mailboxUser?->name;
        }

        if ($detailed) {
            $data['mailbox_user_id'] = $thread->mailbox_user_id;
            $data['mailbox_owner_label'] = $thread->mailboxUser !== null
                ? MailMailboxOwnerCatalog::shortLabel($thread->mailboxUser)
                : null;
            $data['mailbox_owner_name'] = $thread->mailboxUser?->name;
            $data['mailbox_owner_email'] = $thread->mailboxUser?->email;
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
            'body_text' => MailMessageBodyPresenter::plainText($message),
            'body_html' => $message->bodyPurged() ? null : $message->body_html,
            'body_purged' => $message->bodyPurged(),
            'is_important' => (bool) $message->is_important,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'attachments' => $this->serializeMessageAttachments($message),
        ];
    }

    /**
     * @return list<array{name: string, file_size: int|null, mime_type: string|null, download_url: string|null, preview_url: string|null, preview_kind: string|null}>
     */
    private function serializeMessageAttachments(MailMessage $message): array
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->values()
            ->map(function (mixed $attachment, int $index) use ($message): ?array {
                if (! is_array($attachment)) {
                    return null;
                }

                $name = trim((string) ($attachment['original_name'] ?? $attachment['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                $path = trim((string) ($attachment['file_path'] ?? ''));
                $mime = isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null;
                $canPreview = $path !== '' && $this->attachmentPreview->isPreviewable($name, $mime);

                return [
                    'name' => $name,
                    'file_size' => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
                    'mime_type' => $mime,
                    'download_url' => $path !== ''
                        ? route('mail.messages.attachments.download', [$message->id, $index])
                        : null,
                    'preview_url' => $canPreview
                        ? route('mail.messages.attachments.preview', [$message->id, $index])
                        : null,
                    'preview_kind' => $canPreview
                        ? $this->attachmentPreview->previewKind($name, $mime)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{hint: string, max_files: int, max_file_kb: int}
     */
    private function attachmentLimitsPayload(): array
    {
        return [
            'hint' => MailOutboundAttachmentRules::hintRu(),
            'max_files' => max(1, (int) config('mail_sync.outbound_attachments.max_files', 5)),
            'max_file_kb' => max(256, (int) config('mail_sync.outbound_attachments.max_file_kb', 10240)),
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

        return VisibleOrderScope::apply(Order::query())
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

    private function visibleOrderId(MailThread $thread): ?int
    {
        if ($thread->order_id === null) {
            return null;
        }

        $order = $thread->relationLoaded('order') ? $thread->order : null;

        if ($order === null) {
            $order = Order::query()->find($thread->order_id);
        }

        if ($order === null || $order->trashed()) {
            return null;
        }

        return (int) $thread->order_id;
    }

    /**
     * @return array<string, int|null>
     */
    private function mailboxRedirectParams(Request $request): array
    {
        $params = [];

        if ($request->filled('mailbox')) {
            $params['mailbox'] = (int) $request->input('mailbox');
        }

        return $params;
    }
}
