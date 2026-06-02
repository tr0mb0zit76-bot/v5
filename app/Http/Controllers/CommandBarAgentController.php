<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandBarAgentChatRequest;
use App\Http\Requests\CommandBarAgentFeedbackRequest;
use App\Services\Agents\CommandBarAgentService;
use Illuminate\Http\JsonResponse;

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

        $result = $this->agent->chat(
            $user,
            (string) $validated['message'],
            $history,
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
