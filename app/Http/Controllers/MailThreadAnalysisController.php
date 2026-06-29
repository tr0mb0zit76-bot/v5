<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailThreadAnalysisFeedbackRequest;
use App\Models\MailThread;
use App\Models\User;
use App\Services\Commercial\CommercialAiSuggestionLogService;
use App\Services\Commercial\MailMailboxAuthorization;
use App\Services\Commercial\MailThreadAnalysisService;
use App\Support\CrmFeatureCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MailThreadAnalysisController extends Controller
{
    public function __construct(
        private readonly MailThreadAnalysisService $analysis,
        private readonly CommercialAiSuggestionLogService $suggestionLog,
        private readonly MailMailboxAuthorization $mailboxAuth,
    ) {}

    public function summarize(Request $request, MailThread $mailThread): JsonResponse
    {
        $user = $this->authorizeThread($request, $mailThread);

        $result = $this->analysis->summarizeThread(
            $user,
            $mailThread->id,
            (int) $request->integer('message_limit', 20),
        );

        $result['suggestion_key'] = $this->suggestionLog->log(
            $user,
            'summarize',
            $result,
            $mailThread->id,
            $mailThread->lead_id,
        );

        return response()->json($result);
    }

    public function draftReply(Request $request, MailThread $mailThread): JsonResponse
    {
        $user = $this->authorizeThread($request, $mailThread);

        $validated = $request->validate([
            'tone' => ['nullable', 'string', 'in:neutral,friendly,formal,assertive'],
            'message_limit' => ['nullable', 'integer', 'min:5', 'max:40'],
        ]);

        $result = $this->analysis->draftReply(
            $user,
            $mailThread->id,
            (string) ($validated['tone'] ?? 'neutral'),
            (int) ($validated['message_limit'] ?? 20),
        );

        $result['suggestion_key'] = $this->suggestionLog->log(
            $user,
            'draft_reply',
            $result,
            $mailThread->id,
            $mailThread->lead_id,
        );

        return response()->json($result);
    }

    public function suggestNextStep(Request $request, MailThread $mailThread): JsonResponse
    {
        $user = $this->authorizeThread($request, $mailThread);

        if ($mailThread->lead_id === null) {
            throw ValidationException::withMessages([
                'lead_id' => 'К цепочке не привязан лид.',
            ]);
        }

        $result = $this->analysis->suggestLeadNextStep(
            $user,
            (int) $mailThread->lead_id,
            $mailThread->id,
        );

        $result['suggestion_key'] = $this->suggestionLog->log(
            $user,
            'next_step',
            $result,
            $mailThread->id,
            (int) $mailThread->lead_id,
        );

        return response()->json($result);
    }

    public function feedback(MailThreadAnalysisFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(CrmFeatureCatalog::isEnabled('commercial_mail_ai', $user), 404);

        $validated = $request->validated();

        return response()->json($this->suggestionLog->recordFeedback(
            $user,
            (string) $validated['suggestion_key'],
            (string) $validated['rating'],
            isset($validated['comment']) ? (string) $validated['comment'] : null,
        ));
    }

    private function authorizeThread(Request $request, MailThread $mailThread): User
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(CrmFeatureCatalog::isEnabled('commercial_mail_ai', $user), 404);
        abort_unless($this->mailboxAuth->canAccessThread($user, $mailThread), 403);

        return $user;
    }
}
