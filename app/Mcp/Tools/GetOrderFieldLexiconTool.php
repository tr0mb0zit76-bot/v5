<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_order_field_lexicon')]
#[Description('Словарь полей заказа: русские названия, синонимы менеджеров и какой tool использовать (update_order_field / update_order_route_actual).')]
class GetOrderFieldLexiconTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderMcpService $orders,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user): Response {
            return Response::json($this->orders->fieldLexicon());
        });
    }
}
