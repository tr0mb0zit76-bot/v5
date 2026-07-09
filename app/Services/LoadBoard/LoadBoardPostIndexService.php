<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LoadBoardPostIndexService
{
    public const PER_PAGE = 50;

    /**
     * @var list<string>
     */
    public const FILTERS = ['active', 'my', 'buyer', 'has_offers', 'closed', 'all'];

    public function __construct(
        private readonly LoadBoardPostPresenter $presenter,
    ) {}

    public function normalizeFilter(?string $filter): string
    {
        $normalized = strtolower(trim((string) $filter));

        return in_array($normalized, self::FILTERS, true) ? $normalized : 'active';
    }

    public function countActive(): int
    {
        return LoadBoardPost::query()
            ->whereNotIn('status', ['closed', 'cancelled', 'no_options'])
            ->count();
    }

    public function findForPresentation(int $postId): LoadBoardPost
    {
        return LoadBoardPost::query()
            ->with($this->presentationRelations())
            ->withCount('offers')
            ->findOrFail($postId);
    }

    /**
     * @return list<string|callable>
     */
    public function presentationRelations(): array
    {
        return [
            'seller:id,name',
            'buyer:id,name',
            'customer:id,name',
            'lead:id,number,title',
            'order:id,order_number',
            'procurementCase.orderOwner:id,name',
            'procurementCase.buyer:id,name',
            'procurementCase.dispatcher:id,name',
            'procurementCase.order:id,order_number',
            'procurementCase.lead:id,number,title',
            'procurementCase.buyingOwnCompany:id,name',
            'acceptedOffer.carrier:id,name',
            'accepter:id,name',
            'offers.carrier:id,name',
            'offers.creator:id,name',
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function pagePayload(string $filter, User $user, int $page = 1): array
    {
        $paginator = $this->paginate($filter, $user, $page);

        return [
            'data' => $this->presenter->presentMany($paginator->items()),
            'meta' => $this->paginationMeta($paginator),
        ];
    }

    public function paginate(string $filter, User $user, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this
            ->filteredQuery($this->normalizeFilter($filter), $user)
            ->paginate(perPage: $perPage, page: max(1, $page));
    }

    /**
     * @return Builder<LoadBoardPost>
     */
    public function filteredQuery(string $filter, User $user): Builder
    {
        return LoadBoardPost::query()
            ->with($this->presentationRelations())
            ->withCount('offers')
            ->when($filter === 'active', fn (Builder $query) => $query->whereNotIn('status', ['closed', 'cancelled', 'no_options']))
            ->when($filter === 'my', fn (Builder $query) => $query
                ->where('seller_id', $user->id)
                ->whereNotIn('status', ['closed', 'cancelled', 'no_options']))
            ->when($filter === 'buyer', fn (Builder $query) => $query
                ->where('buyer_id', $user->id)
                ->whereNotIn('status', ['closed', 'cancelled', 'no_options']))
            ->when($filter === 'has_offers', fn (Builder $query) => $query
                ->whereHas('offers')
                ->whereNotIn('status', ['closed', 'cancelled', 'no_options']))
            ->when($filter === 'closed', fn (Builder $query) => $query->whereIn('status', ['closed', 'cancelled', 'no_options']))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByRaw("FIELD(status, 'new', 'in_work', 'has_offers', 'seller_review', 'no_options', 'closed', 'cancelled')")
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     */
    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
