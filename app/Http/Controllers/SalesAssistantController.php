<?php

namespace App\Http\Controllers;

use App\Enums\SalesPlaySessionOutcome;
use App\Enums\SalesTrainerDialogQuality;
use App\Http\Requests\ImportSalesBookArticleRequest;
use App\Http\Requests\StoreSalesBookArticleRequest;
use App\Http\Requests\UpdateSalesBookArticleRequest;
use App\Http\Requests\UploadSalesBookAssetRequest;
use App\Models\SalesBookArticle;
use App\Models\SalesScript;
use App\Models\SalesScriptPlaySession;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesAssistantController extends Controller
{
    private const string BOOK_ASSET_PREFIX = 'sales-book-assets/';

    public function book(Request $request): Response
    {
        abort_unless(RoleAccess::canReadSalesBook($request->user()), 403);

        $articles = SalesBookArticle::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $selectedArticleId = $request->integer('article_id');
        $selectedArticle = $articles->firstWhere('id', $selectedArticleId);

        if ($selectedArticle === null) {
            $selectedArticle = $articles->first();
        }

        return Inertia::render('SalesAssistant/Book', [
            'articlesTree' => $this->buildTree($articles),
            'articleOptions' => $articles->map(fn (SalesBookArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'parent_id' => $article->parent_id,
            ])->values(),
            'selectedArticle' => $selectedArticle === null
                ? null
                : [
                    'id' => $selectedArticle->id,
                    'title' => $selectedArticle->title,
                    'parent_id' => $selectedArticle->parent_id,
                    'sort_order' => $selectedArticle->sort_order,
                    'markdown_content' => $selectedArticle->markdown_content,
                    'html_content' => $this->renderMarkdown($selectedArticle->markdown_content),
                    'updated_at' => $selectedArticle->updated_at?->toIso8601String(),
                ],
            'capabilities' => [
                'can_read' => RoleAccess::canReadSalesBook($request->user()),
                'can_comment' => RoleAccess::canCommentSalesBook($request->user()),
                'can_write' => RoleAccess::canWriteSalesBook($request->user()),
            ],
        ]);
    }

    public function storeBookArticle(StoreSalesBookArticleRequest $request): RedirectResponse
    {
        abort_unless(RoleAccess::canWriteSalesBook($request->user()), 403);

        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        $article = SalesBookArticle::query()->create([
            'title' => $data['title'],
            'markdown_content' => $this->resolveMarkdownPayload($data),
            'parent_id' => $parentId,
            'sort_order' => $this->resolveSortOrder($parentId, $data['sort_order'] ?? null),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('sales-assistant.book', ['article_id' => $article->id])->with('flash', [
            'type' => 'success',
            'message' => 'Статья добавлена.',
        ]);
    }

    public function updateBookArticle(UpdateSalesBookArticleRequest $request, SalesBookArticle $salesBookArticle): RedirectResponse
    {
        abort_unless(RoleAccess::canWriteSalesBook($request->user()), 403);

        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        if ($this->isCircularParent($salesBookArticle, $parentId)) {
            return back()->withErrors([
                'parent_id' => 'Нельзя сделать дочерним элементом собственную вложенную статью.',
            ]);
        }

        $salesBookArticle->update([
            'title' => $data['title'],
            'markdown_content' => $this->resolveMarkdownPayload($data),
            'parent_id' => $parentId,
            'sort_order' => $this->resolveSortOrder($parentId, $data['sort_order'] ?? null),
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('sales-assistant.book', ['article_id' => $salesBookArticle->id])->with('flash', [
            'type' => 'success',
            'message' => 'Статья сохранена.',
        ]);
    }

    public function destroyBookArticle(Request $request, SalesBookArticle $salesBookArticle): RedirectResponse
    {
        abort_unless(RoleAccess::canWriteSalesBook($request->user()), 403);

        $salesBookArticle->delete();

        return to_route('sales-assistant.book')->with('flash', [
            'type' => 'success',
            'message' => 'Статья удалена.',
        ]);
    }

    public function importBookArticle(ImportSalesBookArticleRequest $request): RedirectResponse
    {
        abort_unless(RoleAccess::canWriteSalesBook($request->user()), 403);

        $data = $request->validated();
        $uploaded = $request->file('file');
        $markdown = (string) file_get_contents($uploaded->getRealPath());
        $parentId = $data['parent_id'] ?? null;

        $title = $this->extractTitleFromMarkdown($markdown, $uploaded->getClientOriginalName());

        $article = SalesBookArticle::query()->create([
            'title' => $title,
            'markdown_content' => $markdown,
            'parent_id' => $parentId,
            'sort_order' => $this->resolveSortOrder($parentId, $data['sort_order'] ?? null),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('sales-assistant.book', ['article_id' => $article->id])->with('flash', [
            'type' => 'success',
            'message' => 'Markdown-файл импортирован.',
        ]);
    }

    public function uploadBookAsset(UploadSalesBookAssetRequest $request): JsonResponse
    {
        abort_unless(RoleAccess::canWriteSalesBook($request->user()), 403);

        $uploaded = $request->file('file');
        $path = $uploaded->store('sales-book-assets', 'local');
        $url = route('sales-assistant.book.assets.show', ['path' => $path]);
        $name = $uploaded->getClientOriginalName();

        $isImage = str_starts_with((string) $uploaded->getMimeType(), 'image/');
        $markdownSnippet = $isImage
            ? sprintf('![%s](%s)', $name, $url)
            : sprintf('[%s](%s)', $name, $url);

        return response()->json([
            'url' => $url,
            'name' => $name,
            'is_image' => $isImage,
            'markdown' => $markdownSnippet,
        ]);
    }

    public function showBookAsset(Request $request): StreamedResponse
    {
        abort_unless(RoleAccess::canReadSalesBook($request->user()), 403);

        $path = ltrim($request->string('path')->toString(), '/');

        abort_unless(
            str_starts_with($path, self::BOOK_ASSET_PREFIX),
            404
        );
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function trainer(): Response
    {
        $userId = request()->user()?->id;
        $from = CarbonImmutable::now()->subDays(30);

        $trainerQuery = SalesScriptPlaySession::query()
            ->where('is_trainer', true)
            ->where('user_id', $userId)
            ->where('created_at', '>=', $from);

        $summary = [
            'window_days' => 30,
            'total_sessions' => (clone $trainerQuery)->count(),
            'completed_sessions' => (clone $trainerQuery)->whereNotNull('completed_at')->count(),
            'avg_score' => round((float) ((clone $trainerQuery)->whereNotNull('trainer_score')->avg('trainer_score') ?? 0), 1),
            'won_sessions' => (clone $trainerQuery)->where('outcome', 'won')->count(),
            'quote_sessions' => (clone $trainerQuery)->where('outcome', 'quote_sent')->count(),
        ];

        $scripts = SalesScript::query()
            ->with(['versions' => function ($q): void {
                $q->where('is_active', true)->whereNotNull('published_at')->orderByDesc('version_number');
            }])
            ->orderBy('title')
            ->get()
            ->map(function (SalesScript $script): array {
                $version = $script->versions->first();

                return [
                    'id' => $script->id,
                    'title' => $script->title,
                    'description' => $script->description,
                    'channel' => $script->channel,
                    'tags' => $script->tags ?? [],
                    'active_version' => $version ? [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'published_at' => $version->published_at?->toIso8601String(),
                    ] : null,
                ];
            });

        return Inertia::render('SalesAssistant/Trainer', [
            'scripts' => $scripts,
            'trainerSummary' => $summary,
        ]);
    }

    public function trainerAnalytics(Request $request): Response
    {
        $auth = $request->user();
        abort_if($auth === null, 403);

        $canViewAll = $auth->hasRole('admin') || $auth->hasRole('supervisor');

        $daysInput = (int) $request->query('days', '30');
        $allowedDays = [7, 30, 90, 180];
        $days = in_array($daysInput, $allowedDays, true) ? $daysInput : 30;

        $from = CarbonImmutable::now()->startOfDay()->subDays($days);

        $baseQuery = SalesScriptPlaySession::query()
            ->where('is_trainer', true)
            ->where('created_at', '>=', $from);

        if ($canViewAll && $request->filled('user_id')) {
            $filterUserId = $request->integer('user_id');
            if ($filterUserId > 0) {
                $baseQuery->where('user_id', $filterUserId);
            }
        } elseif (! $canViewAll) {
            $baseQuery->where('user_id', $auth->id);
        }

        $profileKeyFilter = $request->filled('trainer_profile_key')
            ? $request->string('trainer_profile_key')->toString()
            : null;
        if ($profileKeyFilter !== null && $profileKeyFilter !== '') {
            $baseQuery->where('trainer_profile_key', $profileKeyFilter);
        }

        if ($request->filled('sales_script_version_id')) {
            $baseQuery->where(
                'sales_script_version_id',
                $request->integer('sales_script_version_id')
            );
        }

        if ($request->filled('outcome')) {
            $raw = $request->string('outcome')->toString();
            $cases = array_column(SalesPlaySessionOutcome::cases(), 'value');
            if (in_array($raw, $cases, true)) {
                $baseQuery->where('outcome', $raw);
            }
        }

        if ($request->filled('trainer_dialog_quality')) {
            $rawQ = $request->string('trainer_dialog_quality')->toString();
            $qualityCases = array_column(SalesTrainerDialogQuality::cases(), 'value');
            if (in_array($rawQ, $qualityCases, true)) {
                $baseQuery->where('trainer_dialog_quality', $rawQ);
            }
        }

        $summary = [
            'window_days' => $days,
            'total_sessions' => (clone $baseQuery)->count(),
            'completed_sessions' => (clone $baseQuery)->whereNotNull('completed_at')->count(),
            'avg_score' => round(
                (float) ((clone $baseQuery)->whereNotNull('trainer_score')->avg('trainer_score') ?? 0),
                1
            ),
            'won_sessions' => (clone $baseQuery)->where('outcome', SalesPlaySessionOutcome::Won)->count(),
            'quote_sessions' => (clone $baseQuery)->where('outcome', SalesPlaySessionOutcome::QuoteSent)->count(),
            'lost_sessions' => (clone $baseQuery)->where('outcome', SalesPlaySessionOutcome::Lost)->count(),
            'progress_sessions' => (clone $baseQuery)->where('outcome', SalesPlaySessionOutcome::Progress)->count(),
            'trainer_dialog_success' => (clone $baseQuery)->where('trainer_dialog_quality', SalesTrainerDialogQuality::Success->value)->count(),
            'trainer_dialog_failure' => (clone $baseQuery)->where('trainer_dialog_quality', SalesTrainerDialogQuality::Failure->value)->count(),
            'trainer_dialog_stuck' => (clone $baseQuery)->where('trainer_dialog_quality', SalesTrainerDialogQuality::Stuck->value)->count(),
        ];

        $dateExpr = match ($baseQuery->getConnection()->getDriverName()) {
            'sqlite' => 'date(created_at)',
            'pgsql' => 'CAST(created_at AS date)',
            default => 'DATE(created_at)',
        };

        /** @var Collection<int, object{d: string, total: int|string|null, completed: int|string|null, avg_score: float|string|null}> */
        $dailyRaw = (clone $baseQuery)
            ->toBase()
            ->selectRaw("{$dateExpr} as d, COUNT(*) as total, SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed, AVG(trainer_score) as avg_score")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('d')
            ->get();

        $daily = $dailyRaw->map(fn (object $row): array => [
            'date' => (string) $row->d,
            'total' => (int) $row->total,
            'completed' => (int) $row->completed,
            'avg_score' => round((float) ($row->avg_score ?? 0), 1),
        ])->values()->all();

        $byProfileRows = (clone $baseQuery)
            ->toBase()
            ->selectRaw('trainer_profile_key as profile_key')
            ->selectRaw('MAX(trainer_profile_title) as profile_title')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(trainer_score) as avg_score')
            ->groupBy('trainer_profile_key')
            ->orderByDesc('total')
            ->get();

        $by_profile = $byProfileRows->map(fn (object $row): array => [
            'profile_key' => $row->profile_key ? (string) $row->profile_key : null,
            'profile_title' => $row->profile_title ? (string) $row->profile_title : '—',
            'total' => (int) $row->total,
            'avg_score' => round((float) ($row->avg_score ?? 0), 1),
        ])->values()->all();

        /** @var Collection<int, SalesScriptPlaySession> */
        $recentSessions = (clone $baseQuery)
            ->with(['user:id,name', 'version:id,version_number,sales_script_id'])
            ->with('version.script:id,title')
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(function (SalesScriptPlaySession $session): array {
                $version = $session->version;
                $scriptTitle = $version?->script?->title;

                return [
                    'id' => $session->id,
                    'created_at' => $session->created_at?->toIso8601String(),
                    'completed_at' => $session->completed_at?->toIso8601String(),
                    'user_id' => $session->user_id,
                    'user_name' => $session->user?->name,
                    'trainer_profile_title' => $session->trainer_profile_title,
                    'outcome' => $session->outcome?->value,
                    'trainer_dialog_quality' => $session->trainer_dialog_quality?->value,
                    'trainer_score' => $session->trainer_score,
                    'script_label' => $scriptTitle
                        ? "{$scriptTitle} · v{$version?->version_number}"
                        : ($version ? "v{$version->version_number}" : '—'),
                ];
            })
            ->values()
            ->all();

        $by_user = [];
        $filterUsers = [];

        if ($canViewAll) {
            $fromUsers = CarbonImmutable::now()->startOfDay()->subYear();
            $participantIds = SalesScriptPlaySession::query()
                ->where('is_trainer', true)
                ->where('created_at', '>=', $fromUsers)
                ->distinct()
                ->pluck('user_id');

            $filterUsers = User::query()
                ->whereIn('id', $participantIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->all();

            $byUserRows = (clone $baseQuery)
                ->toBase()
                ->selectRaw('user_id')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
                ->selectRaw('AVG(trainer_score) as avg_score')
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->get();

            $userNames = User::query()
                ->whereIn('id', $byUserRows->pluck('user_id'))
                ->pluck('name', 'id');

            $by_user = $byUserRows->map(function (object $row) use ($userNames): array {
                $uid = (int) $row->user_id;

                return [
                    'user_id' => $uid,
                    'name' => (string) ($userNames[$uid] ?? '—'),
                    'total' => (int) $row->total,
                    'completed' => (int) $row->completed,
                    'avg_score' => round((float) ($row->avg_score ?? 0), 1),
                ];
            })->values()->all();
        }

        $profileOptionRows = SalesScriptPlaySession::query()
            ->where('is_trainer', true)
            ->whereNotNull('trainer_profile_key')
            ->toBase()
            ->selectRaw('trainer_profile_key as k, MAX(trainer_profile_title) as t')
            ->groupBy('trainer_profile_key')
            ->orderBy('k')
            ->get();

        $profileOptions = $profileOptionRows->map(function (object $row): array {
            $k = (string) $row->k;

            return [
                'key' => $k,
                'title' => (string) ($row->t !== null && $row->t !== '' ? $row->t : $k),
            ];
        })->values()->all();

        $scripts = SalesScript::query()
            ->with(['versions' => function ($q): void {
                $q->where('is_active', true)->whereNotNull('published_at')->orderByDesc('version_number');
            }])
            ->orderBy('title')
            ->get();

        $versionOptions = [];
        foreach ($scripts as $script) {
            $version = $script->versions->first();
            if ($version) {
                $versionOptions[] = [
                    'id' => $version->id,
                    'label' => "{$script->title} · v{$version->version_number}",
                ];
            }
        }

        return Inertia::render('SalesAssistant/TrainerAnalytics', [
            'filters' => [
                'days' => $days,
                'user_id' => $canViewAll && $request->filled('user_id') ? $request->integer('user_id') : null,
                'trainer_profile_key' => $profileKeyFilter ?: null,
                'sales_script_version_id' => $request->filled('sales_script_version_id')
                    ? $request->integer('sales_script_version_id')
                    : null,
                'outcome' => $request->filled('outcome')
                    ? $request->string('outcome')->toString()
                    : null,
                'trainer_dialog_quality' => $request->filled('trainer_dialog_quality')
                    ? $request->string('trainer_dialog_quality')->toString()
                    : null,
                'can_view_all' => $canViewAll,
            ],
            'outcomeOptions' => collect(SalesPlaySessionOutcome::cases())
                ->map(fn (SalesPlaySessionOutcome $o): array => ['value' => $o->value, 'label' => $this->trainerOutcomeLabel($o)])
                ->values()
                ->all(),
            'trainerDialogQualityOptions' => collect(SalesTrainerDialogQuality::cases())
                ->map(fn (SalesTrainerDialogQuality $q): array => ['value' => $q->value, 'label' => $q->label()])
                ->values()
                ->all(),
            'summary' => $summary,
            'daily' => $daily,
            'by_profile' => $by_profile,
            'by_user' => $by_user,
            'recent_sessions' => $recentSessions,
            'filterUsers' => $filterUsers,
            'profile_options' => $profileOptions,
            'version_options' => $versionOptions,
        ]);
    }

    private function trainerOutcomeLabel(SalesPlaySessionOutcome $o): string
    {
        return match ($o) {
            SalesPlaySessionOutcome::Won => 'Успех',
            SalesPlaySessionOutcome::QuoteSent => 'Отправлено КП',
            SalesPlaySessionOutcome::Lost => 'Потерян',
            SalesPlaySessionOutcome::Progress => 'В процессе',
            SalesPlaySessionOutcome::Postponed => 'Отложен',
            SalesPlaySessionOutcome::NoContact => 'Нет контакта',
        };
    }

    /**
     * @return Collection<int, array{id:int,title:string,parent_id:int|null,sort_order:int,children:Collection<int, mixed>}>
     */
    private function buildTree(Collection $articles, ?int $parentId = null): Collection
    {
        return $articles
            ->where('parent_id', $parentId)
            ->sortBy(['sort_order', 'id'])
            ->values()
            ->map(function (SalesBookArticle $article) use ($articles): array {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'parent_id' => $article->parent_id,
                    'sort_order' => $article->sort_order,
                    'children' => $this->buildTree($articles, $article->id),
                ];
            });
    }

    private function renderMarkdown(string $markdown): string
    {
        return Str::of($markdown)->markdown([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ])->toString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveMarkdownPayload(array $data): string
    {
        $htmlContent = trim((string) Arr::get($data, 'html_content', ''));
        if ($htmlContent !== '') {
            $converter = new HtmlConverter([
                'strip_tags' => true,
            ]);

            return trim($converter->convert($htmlContent));
        }

        return (string) Arr::get($data, 'markdown_content', '');
    }

    private function extractTitleFromMarkdown(string $markdown, string $originalFilename): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim((string) pathinfo($originalFilename, PATHINFO_FILENAME)) ?: 'Новая статья';
    }

    private function resolveSortOrder(?int $parentId, ?int $requestedSortOrder): int
    {
        if ($requestedSortOrder !== null) {
            return max(0, $requestedSortOrder);
        }

        $maxSortOrder = (int) SalesBookArticle::query()
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return $maxSortOrder + 1;
    }

    private function isCircularParent(SalesBookArticle $article, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === $article->id) {
            return true;
        }

        $parentsById = SalesBookArticle::query()->pluck('parent_id', 'id');
        $cursor = $parentId;

        while ($cursor !== null) {
            if ($cursor === $article->id) {
                return true;
            }

            $cursor = $parentsById->get($cursor);
        }

        return false;
    }
}
