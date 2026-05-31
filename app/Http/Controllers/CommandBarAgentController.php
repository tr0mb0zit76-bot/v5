<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandBarAgentChatRequest;
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
}
