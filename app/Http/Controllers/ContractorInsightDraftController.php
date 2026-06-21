<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\ContractorInsightDraft;
use App\Models\MailMessage;
use App\Services\Commercial\MailMailboxAuthorization;
use App\Services\Contractor\ContractorInsightDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class ContractorInsightDraftController extends Controller
{
    public function __construct(
        private readonly ContractorInsightDraftService $insightDrafts,
        private readonly MailMailboxAuthorization $mailboxAuth,
    ) {}

    public function extractFromMail(Request $request, Contractor $contractor, MailMessage $mailMessage): JsonResponse
    {
        abort_unless(Schema::hasTable('contractor_insight_drafts'), 422, 'Модуль предложений портрета недоступен.');

        $this->assertMailAccess($request, $mailMessage);

        if ((int) ($mailMessage->thread?->contractor_id ?? 0) !== (int) $contractor->id
            && (int) ($mailMessage->thread?->contractor_id ?? 0) !== 0) {
            abort(422, 'Письмо не связано с этим контрагентом.');
        }

        $drafts = $this->insightDrafts->extractFromMailMessage(
            $mailMessage->loadMissing('thread'),
            $contractor,
            $request->user(),
        );

        return response()->json([
            'drafts' => collect($drafts)->map(fn (ContractorInsightDraft $draft): array => $this->insightDrafts->serializeDraft($draft))->all(),
            'pending_drafts' => $this->insightDrafts->serializePendingForContractor($contractor)->values()->all(),
        ]);
    }

    public function accept(Request $request, Contractor $contractor, ContractorInsightDraft $insightDraft): JsonResponse
    {
        abort_unless(Schema::hasTable('contractor_insight_drafts'), 422, 'Модуль предложений портрета недоступен.');

        Gate::authorize('review', $insightDraft);

        $result = $this->insightDrafts->accept($insightDraft, $contractor, $request->user());

        return response()->json($result);
    }

    public function reject(Request $request, Contractor $contractor, ContractorInsightDraft $insightDraft): JsonResponse
    {
        abort_unless(Schema::hasTable('contractor_insight_drafts'), 422, 'Модуль предложений портрета недоступен.');

        Gate::authorize('review', $insightDraft);

        $result = $this->insightDrafts->reject($insightDraft, $contractor, $request->user());

        return response()->json($result);
    }

    private function assertMailAccess(Request $request, MailMessage $mailMessage): void
    {
        if (! Schema::hasTable('mail_messages')) {
            abort(422, 'Почта недоступна.');
        }

        $user = $request->user();

        if (! $this->mailboxAuth->canAccessMessage($user, $mailMessage)) {
            abort(403, 'Нет доступа к этому письму.');
        }
    }
}
