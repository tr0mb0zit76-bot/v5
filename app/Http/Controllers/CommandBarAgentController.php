<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandBarAgentChatRequest;
use App\Http\Requests\CommandBarAgentFeedbackRequest;
use App\Services\Agents\CommandBarAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class CommandBarAgentController extends Controller
{
    public function __construct(
        private readonly CommandBarAgentService $agent,
    ) {}

    public function chat(CommandBarAgentChatRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var list<array{role: string, content: string}> $history */
        $history = $validated['history'] ?? [];

        /** @var list<UploadedFile> $attachments */
        $attachments = [];
        $rawAttachments = $request->file('attachments');
        if (is_array($rawAttachments)) {
            foreach ($rawAttachments as $file) {
                if ($file instanceof UploadedFile) {
                    $attachments[] = $file;
                }
            }
        } elseif ($rawAttachments instanceof UploadedFile) {
            $attachments[] = $rawAttachments;
        }

        $result = $this->agent->chat(
            $user,
            trim((string) ($validated['message'] ?? '')),
            $history,
            isset($validated['agent_slug']) ? (string) $validated['agent_slug'] : null,
            $attachments,
            $request->boolean('history_extended'),
        );

        return response()->json($result);
    }

    public function feedback(CommandBarAgentFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validated();

        $result = $this->agent->submitFeedback(
            $user,
            (string) $validated['turn_id'],
            (string) $validated['rating'],
            isset($validated['comment']) ? (string) $validated['comment'] : null,
        );

        return response()->json($result);
    }
}
