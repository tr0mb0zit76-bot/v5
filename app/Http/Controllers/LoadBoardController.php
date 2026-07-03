<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoadBoardOfferRequest;
use App\Http\Requests\StoreLoadBoardPostRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\LoadBoard\LoadBoardAtiReadinessService;
use App\Services\LoadBoard\LoadBoardBuyerTaskService;
use App\Support\AtiDictionaryOptionCatalog;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoadBoardController extends Controller
{
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

        $filter = $request->string('filter')->toString();
        if (! in_array($filter, ['active', 'my', 'buyer', 'closed', 'all'], true)) {
            $filter = 'active';
        }

        $prefill = $this->prefillFromRequest($request);

        $posts = LoadBoardPost::query()
            ->with([
                'seller:id,name',
                'buyer:id,name',
                'customer:id,name',
                'lead:id,number,title',
                'order:id,order_number',
                'acceptedOffer.carrier:id,name',
                'accepter:id,name',
                'offers.carrier:id,name',
                'offers.creator:id,name',
            ])
            ->withCount('offers')
            ->when($filter === 'active', fn ($query) => $query->whereNotIn('status', ['closed', 'cancelled', 'no_options']))
            ->when($filter === 'my', fn ($query) => $query->where('seller_id', $user->id))
            ->when($filter === 'buyer', fn ($query) => $query->where('buyer_id', $user->id))
            ->when($filter === 'closed', fn ($query) => $query->whereIn('status', ['closed', 'cancelled', 'no_options']))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByRaw("FIELD(status, 'new', 'in_work', 'has_offers', 'seller_review', 'no_options', 'closed', 'cancelled')")
            ->latest('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (LoadBoardPost $post): array => $this->formatPost($post))
            ->values();

        return Inertia::render('LoadBoard/Index', [
            'posts' => $posts,
            'filter' => $filter,
            'statusLabels' => self::statusLabels(),
            'priorityLabels' => self::priorityLabels(),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'contractors' => Contractor::query()->select(['id', 'name'])->orderBy('name')->limit(500)->get(),
            'leadOptions' => Lead::query()->select(['id', 'number', 'title'])->latest('id')->limit(100)->get(),
            'orderOptions' => Order::query()->select(['id', 'order_number'])->latest('id')->limit(100)->get(),
            'atiDictionaries' => $this->atiDictionaries(),
            'prefill' => $prefill,
        ]);
    }

    public function store(StoreLoadBoardPostRequest $request, LoadBoardBuyerTaskService $buyerTasks): RedirectResponse
    {
        $validated = $request->validated();

        $post = LoadBoardPost::query()->create([
            ...$validated,
            'seller_id' => $request->user()?->id,
            'status' => 'new',
            'customer_rate_currency' => strtoupper((string) ($validated['customer_rate_currency'] ?? 'RUB')),
            'ati_cargo_payload' => $this->atiCargoPayloadFromPostData($validated),
            'published_at' => now(),
        ]);

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

        return back()->with('message', $buyerId === null ? 'Закупщик снят.' : 'Закупщик назначен.');
    }

    public function storeOffer(StoreLoadBoardOfferRequest $request, LoadBoardPost $post): RedirectResponse
    {
        $validated = $request->validated();

        $post->offers()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'carrier_rate_currency' => strtoupper((string) ($validated['carrier_rate_currency'] ?? 'RUB')),
        ]);

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
            $post->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);
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

        if ($post->status !== 'seller_review' || $offer->status !== 'selected') {
            return back()->with('message', 'Сначала выберите вариант перевозчика для согласования.');
        }

        DB::transaction(function () use ($post, $offer, $user): void {
            $post->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);
            $offer->update(['status' => 'approved']);

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
        });

        return back()->with('message', 'Вариант перевозчика принят. Груз закрыт, данные зафиксированы для заказа.');
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

        if ($order->carrier_rate === null && $offer->carrier_rate !== null) {
            $payload['carrier_rate'] = $offer->carrier_rate;
        }

        if (blank($order->carrier_payment_form) && filled($offer->payment_form)) {
            $payload['carrier_payment_form'] = $offer->payment_form;
        }

        $order->forceFill($payload)->save();
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
     * @return array<string, mixed>
     */
    private function formatPost(LoadBoardPost $post): array
    {
        return [
            'id' => $post->id,
            'lead_id' => $post->lead_id,
            'order_id' => $post->order_id,
            'customer_id' => $post->customer_id,
            'seller_id' => $post->seller_id,
            'buyer_id' => $post->buyer_id,
            'accepted_offer_id' => $post->accepted_offer_id,
            'accepted_by' => $post->accepted_by,
            'accepted_at' => $post->accepted_at?->toDateTimeString(),
            'status' => $post->status,
            'priority' => $post->priority,
            'title' => $post->title,
            'loading_location' => $post->loading_location,
            'unloading_location' => $post->unloading_location,
            'loading_date' => $post->loading_date?->toDateString(),
            'unloading_date' => $post->unloading_date?->toDateString(),
            'cargo_name' => $post->cargo_name,
            'ati_cargo_name' => $post->ati_cargo_name,
            'cargo_weight' => $post->cargo_weight,
            'cargo_volume' => $post->cargo_volume,
            'cargo_type_id' => $post->cargo_type_id,
            'cargo_type' => $post->cargo_type,
            'cargo_type_label' => $post->cargo_type_label,
            'pack_type_id' => $post->pack_type_id,
            'package_type' => $post->package_type,
            'pack_type_label' => $post->pack_type_label,
            'package_count' => $post->package_count,
            'loading_type_id' => $post->loading_type_id,
            'loading_type_code' => $post->loading_type_code,
            'loading_type_label' => $post->loading_type_label,
            'loading_type_items' => $post->loading_type_items ?? [],
            'truck_body_type_id' => $post->truck_body_type_id,
            'truck_body_type_code' => $post->truck_body_type_code,
            'truck_body_type_label' => $post->truck_body_type_label,
            'truck_body_type_items' => $post->truck_body_type_items ?? [],
            'trailer_type_id' => $post->trailer_type_id,
            'trailer_type_code' => $post->trailer_type_code,
            'trailer_type_label' => $post->trailer_type_label,
            'trailer_type_items' => $post->trailer_type_items ?? [],
            'length' => $post->length,
            'width' => $post->width,
            'height' => $post->height,
            'diameter' => $post->diameter,
            'is_hazardous' => $post->is_hazardous,
            'hazard_class' => $post->hazard_class,
            'needs_temperature' => $post->needs_temperature,
            'temp_min' => $post->temp_min,
            'temp_max' => $post->temp_max,
            'is_oversized' => $post->is_oversized,
            'is_fragile' => $post->is_fragile,
            'hs_code' => $post->hs_code,
            'ati_cargo_payload' => $post->ati_cargo_payload ?? [],
            'transport_type' => $post->transport_type,
            'customer_rate' => $post->customer_rate,
            'customer_rate_currency' => $post->customer_rate_currency,
            'target_carrier_rate' => $post->target_carrier_rate,
            'payment_form' => $post->payment_form,
            'requirements' => $post->requirements,
            'seller_comment' => $post->seller_comment,
            'metadata' => $post->metadata ?? [],
            'published_at' => $post->published_at?->toDateTimeString(),
            'taken_at' => $post->taken_at?->toDateTimeString(),
            'closed_at' => $post->closed_at?->toDateTimeString(),
            'updated_at' => $post->updated_at?->toDateTimeString(),
            'seller' => $post->seller?->only(['id', 'name']),
            'buyer' => $post->buyer?->only(['id', 'name']),
            'accepted_offer' => $post->acceptedOffer?->only(['id', 'carrier_id', 'carrier_rate', 'carrier_rate_currency', 'payment_form']),
            'accepter' => $post->accepter?->only(['id', 'name']),
            'customer' => $post->customer?->only(['id', 'name']),
            'lead' => $post->lead?->only(['id', 'number', 'title']),
            'order' => $post->order?->only(['id', 'order_number']),
            'offers_count' => $post->offers_count,
            'offers' => $post->offers
                ->sortByDesc(fn (LoadBoardOffer $offer): int => match ($offer->status) {
                    'approved' => 3,
                    'selected' => 2,
                    default => 1,
                })
                ->map(fn (LoadBoardOffer $offer): array => [
                    'id' => $offer->id,
                    'carrier_id' => $offer->carrier_id,
                    'status' => $offer->status,
                    'carrier_rate' => $offer->carrier_rate,
                    'carrier_rate_currency' => $offer->carrier_rate_currency,
                    'payment_form' => $offer->payment_form,
                    'available_date' => $offer->available_date?->toDateString(),
                    'carrier_contact' => $offer->carrier_contact,
                    'conditions' => $offer->conditions,
                    'comment' => $offer->comment,
                    'selected_at' => $offer->selected_at?->toDateTimeString(),
                    'carrier' => $offer->carrier?->only(['id', 'name']),
                    'creator' => $offer->creator?->only(['id', 'name']),
                ])
                ->values()
                ->all(),
        ];
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
}
