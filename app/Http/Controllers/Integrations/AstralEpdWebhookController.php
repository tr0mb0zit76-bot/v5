<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\AstralEpdWebhookRequest;
use App\Services\Epd\AstralEpdInboundService;
use Illuminate\Http\JsonResponse;

class AstralEpdWebhookController extends Controller
{
    public function __invoke(AstralEpdWebhookRequest $request, AstralEpdInboundService $service): JsonResponse
    {
        $document = $service->process($request->validated());

        return response()->json([
            'ok' => true,
            'matched' => $document !== null,
            'document_id' => $document?->getKey(),
        ]);
    }
}
