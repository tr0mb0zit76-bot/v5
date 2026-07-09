<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoadBoardCarrierPoolCandidateRequest;
use App\Http\Requests\StoreLoadBoardOfferRequest;
use App\Http\Requests\StoreLoadBoardPostRequest;
use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Lead;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\LoadBoard\LoadBoardAdvisorService;
use App\Services\LoadBoard\LoadBoardAtiReadinessService;
use App\Services\LoadBoard\LoadBoardBuyerTaskService;
use App\Services\LoadBoard\LoadBoardCarrierPoolCandidateService;
use App\Services\LoadBoard\LoadBoardCarrierPoolService;
use App\Services\LoadBoard\LoadBoardPostIndexService;
use App\Services\LoadBoard\LoadBoardPostPresenter;
use App\Services\LoadBoard\LoadBoardRateObservationService;
use App\Services\LoadBoard\ProcurementCaseLinkService;
use App\Services\LoadBoard\ProcurementCaseSyncService;
use App\Support\AtiDictionaryOptionCatalog;
use App\Support\LoadBoardOfferSource;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoadBoardController extends Controller
{
    public function __construct(
        private readonly LoadBoardPostIndexService $postIndex,
        private readonly LoadBoardPostPresenter $postPresenter,
        private readonly LoadBoardRateObservationService $rateObservations,
        private readonly LoadBoardAdvisorService $advisor,
        private readonly LoadBoardCarrierPoolService $carrierPool,
        private readonly LoadBoardCarrierPoolCandidateService $carrierPoolCandidates,
        private readonly ProcurementCaseSyncService $procurementCases,
        private readonly ProcurementCaseLinkService $procurementCaseLinks,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'new' => 'Новый',
            'in_work' => 'В работе',
            'has_offers' => 'Есть варианты',
            'seller_review' => 'На согласовании',
            'closed' => 'Закрыт',
            'no_options' => 'Без вариантов',
            'cancelled' => 'Отменён',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityLabels(): array
    {
        return [
            'low' => 'Низкий',
            'normal' => 'Обычный',
            'high' => 'Высокий',
            'urgent' => 'Срочно',
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $filter = $this->postIndex->normalizeFilter($request->string('filter')->toString());
        $prefill = $this->prefillFromRequest($request);

        return Inertia::render('LoadBoard/Index', [
            'posts' => $this->postIndex->pagePayload($filter, $user),
            'activePostsCount' => $this->postIndex->countActive(),
            'filter' => $filter,
            'statusLabels' => self::statusLabels(),
            'priorityLabels' => self::priorityLabels(),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'contractors' => Contractor::query()->select(['id', 'name'])->orderBy('name')->limit(500)->get(),
            'leadOptions' => Lead::query()->select(['id', 'number', 'title'])->latest('id')->limit(100)->get(),
            'orderOptions' => Order::query()->select(['id', 'order_number'])->latest('id')->limit(100)->get(),
            'atiDictionaries' => $this->atiDictionaries(),
            'offerSourceOptions' => LoadBoardOfferSource::labels(),
            'prefill' => $prefill,
        ]);
    }

    public function rows(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'filter' => ['sometimes', 'string', Rule::in(LoadBoardPostIndexService::FILTERS)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $filter = $this->postIndex->normalizeFilter($validated['filter'] ?? 'active');
        $page = (int) ($validated['page'] ?? 1);

        return response()->json($this->postIndex->pagePayload($filter, $user, $page));
    }

    public function show(Request $request, LoadBoardPost $post): Response
    {
        abort_if($request->user() === null, 403);

        $post = $this->postIndex->findForPresentation($post->id);
        $presented = $this->postPresenter->present($post);
        $advisor = $this->advisor->advise($post, $this->carrierPool);

        return Inertia::render('LoadBoard/Show', $this->casePageProps($request, $presented, $advisor));
    }

    public function advisor(Request $request, LoadBoardPost $post): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $post = $this->postIndex->findForPresentation($post->id);

        return response()->json([
            'post_id' => $post->id,
            'advisor' => $this->advisor->advise($post, $this->carrierPool),
        ]);
    }

    public function insights(Request $request, LoadBoardPost $post): JsonResponse
    {
        abort_if($request->user() === null, 403);

        return response()->json([
            'post_id' => $post->id,
            'insights' => $this->rateObservations->corridorInsightsForPost($post),
            'advisor' => $this->advisor->advise($post, $this->carrierPool),
        ]);
    }

    public function store(StoreLoadBoardPostRequest $request, LoadBoardBuyerTaskService $buyerTasks): RedirectResponse
    {
        $validated = $request->validated();
        $sellerId = $this->resolveSellerIdForPost($validated, $request->user());

        $post = LoadBoardPost::query()->create([
            ...$validated,
            'seller_id' => $sellerId,
            'status' => 'new',
            'customer_rate_currency' => strtoupper((string) ($validated['customer_rate_currency'] ?? 'RUB')),
            'ati_cargo_payload' => $this->atiCargoPayloadFromPostData($validated),
            'published_at' => now(),
        ]);

        $this->procurementCases->ensureForPost($post->fresh());
        $buyerTasks->ensureForPost($post, $request->user());

        return to_route('load-board.index')->with('message', 'Груз опубликован на внутренней бирже.');
    }

    public function take(Request $request, LoadBoardPost $post, LoadBoardBuyerTaskService $buyerTasks): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            return back()->with('message', 'Закрытый груз нельзя взять в работу.');
        }

        $post->update([
            'buyer_id' => $user->id,
            'status' => 'in_work',
            'taken_at' => $post->taken_at ?? now(),
        ]);

        $buyerTasks->ensureForPost($post->fresh(), $user);

        $this->procurementCases->syncPostStatus($post->fresh());

        return back()->with('message', 'Груз взят в работу.');
    }

    public function release(Request $request, LoadBoardPost $post): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        abort_unless($post->buyer_id === $user->id || $post->seller_id === $user->id, 403);

        $post->update([
            'buyer_id' => null,
            'status' => $post->offers()->exists() ? 'has_offers' : 'new',
            'taken_at' => null,
        ]);

        return back()->with('message', 'Груз возвращён в общий список.');
    }

    public function assignBuyer(Request $request, LoadBoardPost $post, LoadBoardBuyerTaskService $buyerTasks): RedirectResponse
    {
        $validated = $request->validate([
            'buyer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            return back()->with('message', 'У закрытого груза нельзя менять закупщика.');
        }

        $buyerId = $validated['buyer_id'] ?? null;

        $post->update([
            'buyer_id' => $buyerId,
            'status' => $buyerId === null
                ? ($post->offers()->exists() ? 'has_offers' : 'new')
                : 'in_work',
            'taken_at' => $buyerId === null ? null : ($post->taken_at ?? now()),
        ]);

        if ($buyerId !== null) {
            $buyerTasks->ensureForPost($post->fresh(), $request->user());
        }

        $this->procurementCases->syncPostStatus($post->fresh());

        return back()->with('message', $buyerId === null ? 'Закупщик снят.' : 'Закупщик назначен.');
    }

    public function storeOffer(StoreLoadBoardOfferRequest $request, LoadBoardPost $post): RedirectResponse
    {
        $validated = $request->validated();

        $offer = $post->offers()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'source' => $validated['source'] ?? LoadBoardOfferSource::INTERNAL_CRM,
            'carrier_rate_currency' => strtoupper((string) ($validated['carrier_rate_currency'] ?? 'RUB')),
        ]);

        $this->rateObservations->recordOfferCreated($post, $offer);

        if (! in_array($post->status, ['closed', 'cancelled', 'no_options'], true)) {
            $post->update([
                'status' => 'has_offers',
                'buyer_id' => $post->buyer_id ?? $request->user()?->id,
                'taken_at' => $post->taken_at ?? now(),
            ]);
        }

        return back()->with('message', 'Вариант перевозчика добавлен.');
    }

    public function selectOffer(Request $request, LoadBoardPost $post, LoadBoardOffer $offer): RedirectResponse
    {
        abort_unless($offer->load_board_post_id === $post->id, 404);

        DB::transaction(function () use ($post, $offer): void {
            $rejectedOffers = $post->offers()->where('id', '!=', $offer->id)->get();
            $post->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);
            $this->rateObservations->markOffersOutcome($rejectedOffers, 'not_selected');
            $offer->update([
                'status' => 'selected',
                'selected_at' => now(),
            ]);
            $post->update([
                'status' => 'seller_review',
            ]);
        });

        return back()->with('message', 'Вариант выбран для согласования.');
    }

    public function approveOffer(Request $request, LoadBoardPost $post, LoadBoardOffer $offer): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($offer->load_board_post_id === $post->id, 404);

        $post->refresh();
        $offer->refresh();

        if ($post->status !== 'seller_review' || $offer->status !== 'selected') {
            return back()->with('message', 'Сначала выберите вариант перевозчика для согласования.');
        }

        DB::transaction(function () use ($post, $offer, $user): void {
            $rejectedOffers = $post->offers()->where('id', '!=', $offer->id)->get();
            $post->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);
            $this->rateObservations->markOffersOutcome($rejectedOffers, 'rejected');
            $offer->update(['status' => 'approved']);
            $this->rateObservations->markOfferOutcome($offer, 'approved');

            $metadata = is_array($post->metadata) ? $post->metadata : [];
            $metadata['accepted_offer'] = $this->acceptedOfferMetadata($offer);

            $post->update([
                'status' => 'closed',
                'accepted_offer_id' => $offer->id,
                'accepted_by' => $user->id,
                'accepted_at' => now(),
                'closed_at' => now(),
                'metadata' => $metadata,
            ]);

            $this->applyAcceptedOfferToOrder($post->fresh(), $offer);
            $this->closeBuyerTask($post);
            $this->procurementCases->syncPostStatus($post->fresh());
        });

        return back()->with('message', 'Вариант перевозчика принят. Груз закрыт, данные зафиксированы для заказа.');
    }

    public function storeCarrierPoolCandidate(
        StoreLoadBoardCarrierPoolCandidateRequest $request,
        LoadBoardPost $post,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);

        $this->carrierPoolCandidates->add($post, $request->validated(), $request->user());

        return back()->with('message', 'Кандидат добавлен в пул перевозчиков.');
    }

    public function destroyCarrierPoolCandidate(
        Request $request,
        LoadBoardPost $post,
        string $candidate,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);

        $this->carrierPoolCandidates->remove($post, $candidate);

        return back()->with('message', 'Кандидат удалён из пула.');
    }

    public function attachProcurementCaseLink(Request $request, LoadBoardPost $post): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['order', 'lead'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $case = $this->procurementCases->caseForPost($post) ?? $this->procurementCases->ensureForPost($post);
        if ($case === null) {
            return back()->with('message', 'Кейс закупки недоступен.');
        }

        if ($validated['type'] === 'order') {
            $this->procurementCaseLinks->attachOrder($case, (int) $validated['id']);
        } else {
            $this->procurementCaseLinks->attachLead($case, (int) $validated['id']);
        }

        return back()->with('message', 'Связь добавлена в кейс закупки.');
    }

    public function updateStatus(Request $request, LoadBoardPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['closed', 'no_options', 'cancelled'])],
        ]);

        $post->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : $post->closed_at,
        ]);

        if (in_array($validated['status'], ['closed', 'cancelled', 'no_options'], true)) {
            $this->rateObservations->closeOpenObservationsForPost($post, 'expired');
        }

        return back()->with('message', 'Статус груза обновлён.');
    }

    public function prepareAti(Request $request, LoadBoardPost $post, LoadBoardAtiReadinessService $readiness): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        $preview = $readiness->preview($post);

        return back()->with('flash', [
            'message' => $preview['ready']
                ? 'Груз готов к отправке на ATI. Проверьте payload перед внешней публикацией.'
                : 'Груз пока не готов к ATI: заполните обязательные поля.',
            'load_board_ati_preview' => $preview,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptedOfferMetadata(LoadBoardOffer $offer): array
    {
        $offer->loadMissing(['carrier:id,name', 'creator:id,name']);

        return [
            'offer_id' => $offer->id,
            'carrier_id' => $offer->carrier_id,
            'carrier_name' => $offer->carrier?->name,
            'carrier_rate' => $offer->carrier_rate,
            'carrier_rate_currency' => $offer->carrier_rate_currency,
            'payment_form' => $offer->payment_form,
            'available_date' => $offer->available_date?->toDateString(),
            'carrier_contact' => $offer->carrier_contact,
            'conditions' => $offer->conditions,
            'comment' => $offer->comment,
            'buyer_id' => $offer->created_by,
            'buyer_name' => $offer->creator?->name,
            'accepted_at' => now()->toIso8601String(),
        ];
    }

    private function applyAcceptedOfferToOrder(LoadBoardPost $post, LoadBoardOffer $offer): void
    {
        if ($post->order_id === null) {
            return;
        }

        $order = Order::query()->find($post->order_id);
        if (! $order instanceof Order) {
            return;
        }

        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata['load_board_accepted_offer'] = [
            'post_id' => $post->id,
            ...$this->acceptedOfferMetadata($offer),
        ];

        $payload = ['metadata' => $metadata];

        if ($order->carrier_id === null && $offer->carrier_id !== null) {
            $payload['carrier_id'] = $offer->carrier_id;
        }

        if (
            Schema::hasColumn('orders', 'carrier_rate')
            && $order->carrier_rate === null
            && $offer->carrier_rate !== null
        ) {
            $payload['carrier_rate'] = $offer->carrier_rate;
        }

        if (
            Schema::hasColumn('orders', 'carrier_payment_form')
            && blank($order->carrier_payment_form)
            && filled($offer->payment_form)
        ) {
            $payload['carrier_payment_form'] = $offer->payment_form;
        }

        $order->forceFill($payload)->save();

        if (
            ! Schema::hasColumn('orders', 'carrier_rate')
            && $offer->carrier_rate !== null
            && Schema::hasTable('financial_terms')
        ) {
            $this->syncAcceptedOfferToFinancialTerms($order, $offer);
        }
    }

    private function syncAcceptedOfferToFinancialTerms(Order $order, LoadBoardOffer $offer): void
    {
        $financialTerm = FinancialTerm::query()->firstOrNew(['order_id' => $order->id]);
        $costs = is_array($financialTerm->contractors_costs) ? $financialTerm->contractors_costs : [];

        if ($costs !== []) {
            return;
        }

        $financialTerm->contractors_costs = [[
            'contractor_id' => $offer->carrier_id,
            'amount' => (float) $offer->carrier_rate,
            'payment_form' => $offer->payment_form,
            'currency' => strtoupper((string) ($offer->carrier_rate_currency ?: 'RUB')),
        ]];
        $financialTerm->save();
    }

    private function closeBuyerTask(LoadBoardPost $post): void
    {
        Task::query()
            ->where('meta->load_board_post_id', $post->id)
            ->whereIn('status', ['new', 'in_progress', 'review', 'on_hold'])
            ->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function prefillFromRequest(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        if ($request->filled('from_lead') && RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            $lead = Lead::query()
                ->with(['counterparty:id,name', 'cargoItems', 'routePoints'])
                ->find((int) $request->query('from_lead'));

            if ($lead instanceof Lead) {
                return $this->prefillFromLead($lead);
            }
        }

        if ($request->filled('from_order') && RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            $order = Order::query()
                ->with(['customer:id,name', 'cargoItems', 'routePoints'])
                ->find((int) $request->query('from_order'));

            if ($order instanceof Order) {
                return $this->prefillFromOrder($order);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function prefillFromLead(Lead $lead): array
    {
        $loadingPoint = $this->routePointByType($lead->routePoints, 'loading');
        $unloadingPoint = $this->routePointByType($lead->routePoints, 'unloading', last: true);
        $cargo = $lead->cargoItems->first();
        $weightKg = $cargo?->weight_kg;

        return [
            'source' => 'lead',
            'source_label' => 'Лид #'.($lead->number ?? $lead->id),
            'lead_id' => $lead->id,
            'order_id' => null,
            'customer_id' => $lead->counterparty_id,
            'priority' => 'normal',
            'title' => $lead->title ?: 'Груз по лиду #'.($lead->number ?? $lead->id),
            'loading_location' => $loadingPoint?->address ?? $lead->loading_location,
            'unloading_location' => $unloadingPoint?->address ?? $lead->unloading_location,
            'loading_date' => $loadingPoint?->planned_date?->toDateString() ?? $lead->planned_shipping_date?->toDateString(),
            'unloading_date' => $unloadingPoint?->planned_date?->toDateString(),
            'cargo_name' => $cargo?->name,
            ...$this->atiPrefillFromCargo($cargo),
            'cargo_weight' => $weightKg !== null ? round(((float) $weightKg) / 1000, 2) : null,
            'cargo_volume' => $cargo?->volume_m3,
            'transport_type' => $lead->transport_type ?? $cargo?->cargo_type,
            'customer_rate' => $lead->target_price,
            'customer_rate_currency' => $lead->target_currency ?: 'RUB',
            'target_carrier_rate' => null,
            'payment_form' => $lead->customer_payment_form,
            'requirements' => $cargo?->description,
            'seller_comment' => 'Черновик создан из лида #'.($lead->number ?? $lead->id).'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prefillFromOrder(Order $order): array
    {
        $loadingPoint = $this->routePointByType($order->routePoints, 'loading');
        $unloadingPoint = $this->routePointByType($order->routePoints, 'unloading', last: true);
        $cargo = $order->cargoItems->first();
        $transportType = collect([
            $cargo?->truck_body_type_label,
            $cargo?->trailer_type_label,
            $cargo?->cargo_type_label,
        ])->filter()->implode(', ');

        return [
            'source' => 'order',
            'source_label' => 'Заказ '.($order->order_number ?? '#'.$order->id),
            'lead_id' => $order->lead_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'priority' => 'normal',
            'title' => 'Закупка перевозчика по заказу '.($order->order_number ?? '#'.$order->id),
            'loading_location' => $loadingPoint?->address,
            'unloading_location' => $unloadingPoint?->address,
            'loading_date' => $loadingPoint?->planned_date?->toDateString() ?? $order->loading_date?->toDateString(),
            'unloading_date' => $unloadingPoint?->planned_date?->toDateString() ?? $order->unloading_date?->toDateString(),
            'cargo_name' => $cargo?->title ?? $cargo?->ati_cargo_name,
            ...$this->atiPrefillFromCargo($cargo),
            'cargo_weight' => $cargo?->weight,
            'cargo_volume' => $cargo?->volume,
            'transport_type' => $transportType !== '' ? $transportType : null,
            'customer_rate' => $order->customer_rate,
            'customer_rate_currency' => 'RUB',
            'target_carrier_rate' => $order->carrier_rate,
            'payment_form' => $order->customer_payment_form,
            'requirements' => $cargo?->special_instructions ?? $cargo?->description,
            'seller_comment' => 'Черновик создан из заказа '.($order->order_number ?? '#'.$order->id).'.',
        ];
    }

    /**
     * @return array<string, list<array{value:int, code:string|null, label:string, ati_id:int|null}>>
     */
    private function atiDictionaries(): array
    {
        return [
            'cargoTypes' => AtiDictionaryOptionCatalog::options('cargo_type', AtiDictionaryOptionCatalog::fallbackCargoTypeOptions()),
            'packageTypes' => AtiDictionaryOptionCatalog::options('package_type', AtiDictionaryOptionCatalog::fallbackPackageTypeOptions()),
            'loadingTypes' => AtiDictionaryOptionCatalog::options('loading_type', AtiDictionaryOptionCatalog::fallbackLoadingTypeOptions()),
            'truckBodyTypes' => AtiDictionaryOptionCatalog::options('truck_body_type', AtiDictionaryOptionCatalog::fallbackTruckBodyTypeOptions()),
            'trailerTypes' => AtiDictionaryOptionCatalog::options('trailer_type', AtiDictionaryOptionCatalog::fallbackTrailerTypeOptions()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function atiPrefillFromCargo(?object $cargo): array
    {
        if ($cargo === null) {
            return [
                'loading_type_items' => [],
                'truck_body_type_items' => [],
                'trailer_type_items' => [],
            ];
        }

        return [
            'ati_cargo_name' => $cargo->ati_cargo_name ?? $cargo->name ?? $cargo->title ?? null,
            'cargo_type_id' => $cargo->cargo_type_id ?? null,
            'cargo_type' => $cargo->cargo_type ?? null,
            'cargo_type_label' => $cargo->cargo_type_label ?? null,
            'pack_type_id' => $cargo->pack_type_id ?? null,
            'package_type' => $cargo->package_type ?? $cargo->packing_type ?? null,
            'pack_type_label' => $cargo->pack_type_label ?? null,
            'package_count' => $cargo->package_count ?? $cargo->pallet_count ?? null,
            'loading_type_id' => $cargo->loading_type_id ?? null,
            'loading_type_code' => $cargo->loading_type_code ?? null,
            'loading_type_label' => $cargo->loading_type_label ?? null,
            'loading_type_items' => $this->dictionaryItems($cargo->loading_type_items ?? null),
            'truck_body_type_id' => $cargo->truck_body_type_id ?? null,
            'truck_body_type_code' => $cargo->truck_body_type_code ?? null,
            'truck_body_type_label' => $cargo->truck_body_type_label ?? null,
            'truck_body_type_items' => $this->dictionaryItems($cargo->truck_body_type_items ?? null),
            'trailer_type_id' => $cargo->trailer_type_id ?? null,
            'trailer_type_code' => $cargo->trailer_type_code ?? null,
            'trailer_type_label' => $cargo->trailer_type_label ?? null,
            'trailer_type_items' => $this->dictionaryItems($cargo->trailer_type_items ?? null),
            'length' => $cargo->length ?? $cargo->length_m ?? null,
            'width' => $cargo->width ?? $cargo->width_m ?? null,
            'height' => $cargo->height ?? $cargo->height_m ?? null,
            'diameter' => $cargo->diameter ?? $cargo->diameter_m ?? null,
            'is_hazardous' => (bool) ($cargo->is_hazardous ?? $cargo->dangerous_goods ?? false),
            'hazard_class' => $cargo->hazard_class ?? $cargo->dangerous_class ?? null,
            'needs_temperature' => (bool) ($cargo->needs_temperature ?? false),
            'temp_min' => $cargo->temp_min ?? null,
            'temp_max' => $cargo->temp_max ?? null,
            'is_oversized' => (bool) ($cargo->is_oversized ?? false),
            'is_fragile' => (bool) ($cargo->is_fragile ?? false),
            'hs_code' => $cargo->hs_code ?? null,
            'ati_cargo_payload' => is_array($cargo->ati_cargo_payload ?? null) ? $cargo->ati_cargo_payload : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function atiCargoPayloadFromPostData(array $data): array
    {
        $payload = array_filter([
            'name' => $data['ati_cargo_name'] ?? $data['cargo_name'] ?? null,
            'cargoTypeId' => $data['cargo_type_id'] ?? null,
            'cargoType' => $data['cargo_type'] ?? null,
            'cargoTypeName' => $data['cargo_type_label'] ?? null,
            'weight' => $this->weightPayload($data['cargo_weight'] ?? null),
            'volume' => $data['cargo_volume'] ?? null,
            'sizes' => $this->sizesPayload($data),
            'packaging' => $this->packagingPayload($data),
            'loading' => $this->loadingPayload($data),
            'transport' => $this->transportPayload($data),
            'hazard' => $this->hazardPayload($data),
            'temperature' => $this->temperaturePayload($data),
            'flags' => $this->flagsPayload($data),
            'hsCode' => $data['hs_code'] ?? null,
            'description' => $data['requirements'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        $customPayload = is_array($data['ati_cargo_payload'] ?? null) ? $data['ati_cargo_payload'] : [];

        return array_replace_recursive($payload, $customPayload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function weightPayload(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return [
            'value' => (float) $value,
            'unit' => 't',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function sizesPayload(array $data): ?array
    {
        $payload = array_filter([
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'diameter' => $data['diameter'] ?? null,
            'unit' => 'm',
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return count($payload) > 1 ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function packagingPayload(array $data): ?array
    {
        $payload = array_filter([
            'packTypeId' => $data['pack_type_id'] ?? null,
            'packType' => $data['package_type'] ?? null,
            'packTypeName' => $data['pack_type_label'] ?? null,
            'places' => $data['package_count'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function loadingPayload(array $data): ?array
    {
        $payload = array_filter([
            'loadingTypeId' => $data['loading_type_id'] ?? null,
            'loadingType' => $data['loading_type_code'] ?? null,
            'loadingTypeName' => $data['loading_type_label'] ?? null,
            'loadingTypes' => $this->dictionaryItems($data['loading_type_items'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function transportPayload(array $data): ?array
    {
        $payload = array_filter([
            'truckBodyTypeId' => $data['truck_body_type_id'] ?? null,
            'truckBodyType' => $data['truck_body_type_code'] ?? null,
            'truckBodyTypeName' => $data['truck_body_type_label'] ?? null,
            'truckBodyTypes' => $this->dictionaryItems($data['truck_body_type_items'] ?? null),
            'trailerTypeId' => $data['trailer_type_id'] ?? null,
            'trailerType' => $data['trailer_type_code'] ?? null,
            'trailerTypeName' => $data['trailer_type_label'] ?? null,
            'trailerTypes' => $this->dictionaryItems($data['trailer_type_items'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function hazardPayload(array $data): ?array
    {
        if (! ($data['is_hazardous'] ?? false) && blank($data['hazard_class'] ?? null)) {
            return null;
        }

        return array_filter([
            'isHazardous' => (bool) ($data['is_hazardous'] ?? false),
            'class' => $data['hazard_class'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function temperaturePayload(array $data): ?array
    {
        if (! ($data['needs_temperature'] ?? false) && blank($data['temp_min'] ?? null) && blank($data['temp_max'] ?? null)) {
            return null;
        }

        return array_filter([
            'required' => (bool) ($data['needs_temperature'] ?? false),
            'min' => $data['temp_min'] ?? null,
            'max' => $data['temp_max'] ?? null,
            'unit' => 'C',
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>|null
     */
    private function flagsPayload(array $data): ?array
    {
        $flags = array_filter([
            'oversized' => (bool) ($data['is_oversized'] ?? false),
            'fragile' => (bool) ($data['is_fragile'] ?? false),
        ]);

        return $flags === [] ? null : $flags;
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string|null}>
     */
    private function dictionaryItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => $item['id'] ?? null,
                'code' => $item['code'] ?? null,
                'label' => $item['label'] ?? null,
            ])
            ->filter(fn (array $item): bool => $item['id'] !== null || $item['code'] !== null || $item['label'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $points
     */
    private function routePointByType(Collection $points, string $type, bool $last = false): ?object
    {
        $filtered = $points->filter(fn (object $point): bool => ($point->type ?? null) === $type);
        $point = $last ? $filtered->last() : $filtered->first();

        if ($point !== null) {
            return $point;
        }

        return $last ? $points->last() : $points->first();
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $advisor
     * @return array<string, mixed>
     */
    private function casePageProps(Request $request, array $post, array $advisor): array
    {
        return [
            'post' => $post,
            'advisor' => $advisor,
            'carrierPool' => $advisor['carrier_pool'] ?? [],
            'statusLabels' => self::statusLabels(),
            'priorityLabels' => self::priorityLabels(),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'contractors' => Contractor::query()->select(['id', 'name'])->orderBy('name')->limit(500)->get(),
            'leadOptions' => Lead::query()->select(['id', 'number', 'title'])->latest('id')->limit(100)->get(),
            'orderOptions' => Order::query()->select(['id', 'order_number'])->latest('id')->limit(100)->get(),
            'atiDictionaries' => $this->atiDictionaries(),
            'offerSourceOptions' => LoadBoardOfferSource::labels(),
            'atiPreview' => $request->session()->get('flash.load_board_ati_preview'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSellerIdForPost(array $validated, ?User $user): ?int
    {
        $orderId = $validated['order_id'] ?? null;
        if ($orderId !== null) {
            $order = Order::query()
                ->select(['id', 'order_owner_id', 'manager_id'])
                ->find($orderId);

            if ($order instanceof Order) {
                if (Schema::hasColumn('orders', 'order_owner_id') && $order->order_owner_id !== null) {
                    return (int) $order->order_owner_id;
                }

                if ($order->manager_id !== null) {
                    return (int) $order->manager_id;
                }
            }
        }

        return $user?->id;
    }
}
