<?php

namespace App\Mcp\Concerns;

use App\Services\Mcp\AiToolAuditLogger;
use App\Services\Mcp\McpAccessGate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Throwable;

trait LogsMcpToolCalls
{
    protected function withMcpAccess(Request $request, callable $callback): Response
    {
        $gate = app(McpAccessGate::class);
        $audit = app(AiToolAuditLogger::class);
        $toolName = $this->name();

        try {
            $user = $gate->resolveUser($request);
            $response = $callback($user);
            $audit->log($user, $toolName, $request->toArray(), ! $response->isError());

            return $response;
        } catch (Throwable $throwable) {
            if (isset($user)) {
                $audit->log($user, $toolName, $request->toArray(), false, $throwable->getMessage());
            }

            return Response::error($throwable->getMessage());
        }
    }
}
