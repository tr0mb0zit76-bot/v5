<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesBookPageRequest;
use App\Models\SalesBook;
use App\Models\SalesBookPage;
use Inertia\Inertia;

class SalesBookPageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SalesBook $book)
    {
        $pages = $book->pages()
            ->with('author')
            ->orderBy('order_index')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('SalesBook/Pages/Index', [
            'book' => $book,
            'pages' => $pages,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(SalesBook $book)
    {
        $parentPages = $book->pages()
            ->whereNull('parent_id')
            ->orderBy('order_index')
            ->get();

        return Inertia::render('SalesBook/Pages/Create', [
            'book' => $book,
            'parentPages' => $parentPages,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSalesBookPageRequest $request, SalesBook $book)
    {
        $validated = $request->validated();

        $page = $book->pages()->create([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'] ?? null,
            'raw_markdown' => $validated['raw_markdown'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'author_id' => auth()->id(),
            'order_index' => $validated['order_index'] ?? 0,
            'depth' => $validated['depth'] ?? 0,
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ?? ($validated['status'] === 'published' ? now() : null),
        ]);

        return redirect()->route('sales-book.pages.show', $page)
            ->with('success', 'Страница успешно создана');
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesBookPage $salesBookPage)
    {
        $salesBookPage->load(['book', 'author', 'parent', 'children' => function ($query) {
            $query->orderBy('order_index')->orderBy('created_at');
        }]);

        return Inertia::render('SalesBook/Pages/Show', [
            'page' => $salesBookPage,
            'book' => $salesBookPage->book,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesBookPage $salesBookPage)
    {
        $salesBookPage->load(['book', 'parent']);

        $parentPages = $salesBookPage->book->pages()
            ->where('id', '!=', $salesBookPage->id)
            ->whereNull('parent_id')
            ->orderBy('order_index')
            ->get();

        return Inertia::render('SalesBook/Pages/Edit', [
            'page' => $salesBookPage,
            'book' => $salesBookPage->book,
            'parentPages' => $parentPages,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreSalesBookPageRequest $request, SalesBookPage $salesBookPage)
    {
        $validated = $request->validated();

        $salesBookPage->update([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'] ?? $salesBookPage->content,
            'raw_markdown' => $validated['raw_markdown'] ?? $salesBookPage->raw_markdown,
            'excerpt' => $validated['excerpt'] ?? $salesBookPage->excerpt,
            'parent_id' => $validated['parent_id'] ?? $salesBookPage->parent_id,
            'order_index' => $validated['order_index'] ?? $salesBookPage->order_index,
            'depth' => $validated['depth'] ?? $salesBookPage->depth,
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ?? ($validated['status'] === 'published' && ! $salesBookPage->published_at ? now() : $salesBookPage->published_at),
        ]);

        return redirect()->route('sales-book.pages.show', $salesBookPage)
            ->with('success', 'Страница успешно обновлена');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesBookPage $salesBookPage)
    {
        $book = $salesBookPage->book;
        $salesBookPage->delete();

        return redirect()->route('sales-book.books.show', $book)
            ->with('success', 'Страница успешно удалена');
    }
}
