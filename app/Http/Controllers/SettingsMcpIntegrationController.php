<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMcpDataLinksRequest;
use App\Services\McpIntegrationService;
use App\Support\McpIntegrationCatalog;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsMcpIntegrationController extends Controller
{
    public function __construct(
        private readonly McpIntegrationService $integrationService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless($this->integrationService->tablesReady(), 404);

        return Inertia::render('Settings/McpIntegrations/Index', [
            'nodes' => McpIntegrationCatalog::nodes(),
            'links' => $this->integrationService->listLinks(),
        ]);
    }

    public function update(UpdateMcpDataLinksRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->integrationService->syncLinks(
            is_array($validated['links'] ?? null) ? $validated['links'] : [],
        );

        return redirect()
            ->route('settings.mcp-integrations.index')
            ->with('success', 'Связи MCP сохранены.');
    }
}
