<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSalesBookArticleRequest;
use App\Http\Requests\StoreSalesBookArticleRequest;
use App\Http\Requests\UpdateSalesBookArticleRequest;
use App\Http\Requests\UploadSalesBookAssetRequest;
use App\Models\SalesBookArticle;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
        return Inertia::render('SalesAssistant/Trainer');
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
