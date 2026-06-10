<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use App\Support\RoleAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_user_context')]
#[Description('Контекст текущего пользователя CRM: роль, области видимости, scope заказов.')]
class GetUserContextTool extends Tool
{
    use LogsMcpToolCalls;

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user): Response {
            $areas = RoleAccess::userVisibilityAreas($user);

            return Response::json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                    'belongs_to_management' => $user->belongsToManagement(),
                ],
                'visibility_areas' => $areas,
                'orders_scope' => RoleAccess::resolveVisibilityScopeForUser($user, 'orders'),
                'can_view_finance' => app(McpAccessGate::class)->canViewFinance($user),
                'can_management_accounting' => app(McpAccessGate::class)->canAccessManagementAccounting($user),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
