<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_order_portfolio_slice')]
#[Description('Срез портфеля заказов: COUNT/суммы/примеры по формам оплаты заказчика и перевозчика (cash / non_cash / конкретный код), периоду order_date и active_only. Для вопросов «сколько заявок с наличкой у перевозчика и безналом у заказчика». Не для поиска одного номера — для этого search_orders.')]
class GetOrderPortfolioSliceTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderMcpService $orders,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'customer_payment_form' => ['nullable', 'string', 'max:40'],
                'customer_payment_form_group' => ['nullable', 'string', 'in:cash,non_cash,any'],
                'carrier_payment_form' => ['nullable', 'string', 'max:40'],
                'carrier_payment_form_group' => ['nullable', 'string', 'in:cash,non_cash,any'],
                'from_date' => ['nullable', 'date'],
                'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
                'active_only' => ['nullable', 'boolean'],
                'include_examples' => ['nullable', 'boolean'],
                'examples_limit' => ['nullable', 'integer', 'min:1', 'max:15'],
            ]);

            return Response::json(
                $this->orders->portfolioSlice($user, $validated),
            );
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_payment_form' => $schema->string()
                ->description('Точный код формы оплаты заказчика (cash, no_vat, vat_22…). Имеет приоритет над group.'),
            'customer_payment_form_group' => $schema->string()
                ->enum(['cash', 'non_cash', 'any'])
                ->description('Группа: cash | non_cash (всё кроме cash) | any.'),
            'carrier_payment_form' => $schema->string()
                ->description('Точный код формы оплаты перевозчика.'),
            'carrier_payment_form_group' => $schema->string()
                ->enum(['cash', 'non_cash', 'any'])
                ->description('Группа формы перевозчика: cash | non_cash | any.'),
            'from_date' => $schema->string()
                ->description('Начало периода по order_date (YYYY-MM-DD).'),
            'to_date' => $schema->string()
                ->description('Конец периода по order_date (YYYY-MM-DD).'),
            'active_only' => $schema->boolean()
                ->description('Только активные (не cancelled/closed). По умолчанию true.'),
            'include_examples' => $schema->boolean()
                ->description('Вернуть примеры заказов. По умолчанию true.'),
            'examples_limit' => $schema->integer()
                ->description('Число примеров 1–15. По умолчанию 8.')
                ->min(1)
                ->max(15),
        ];
    }
}
