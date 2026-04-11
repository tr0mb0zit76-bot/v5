<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesBookRequest;
use App\Models\SalesBook;
use App\Models\SalesBookCategory;
use Inertia\Inertia;

class SalesBookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $books = SalesBook::with(['author', 'categories'])
            ->withCount('pages')
            ->latest()
            ->get();

        return Inertia::render('SalesBook/Index', [
            'books' => $books,
            'categories' => SalesBookCategory::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('SalesBook/Create', [
            'categories' => SalesBookCategory::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSalesBookRequest $request)
    {
        $validated = $request->validated();

        $book = SalesBook::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'author_id' => auth()->id(),
            'status' => $validated['status'],
            'order_index' => $validated['order_index'] ?? 0,
        ]);

        // Привязываем категории, если они указаны
        if (! empty($validated['category_ids'])) {
            $book->categories()->sync($validated['category_ids']);
        }

        return redirect()->route('sales-book.books.show', $book)
            ->with('success', 'Книга успешно создана');
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesBook $salesBook)
    {
        $salesBook->load(['author', 'categories', 'pages' => function ($query) {
            $query->orderBy('order_index')->orderBy('created_at');
        }]);

        return Inertia::render('SalesBook/Show', [
            'book' => $salesBook,
            'categories' => SalesBookCategory::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesBook $salesBook)
    {
        $salesBook->load('categories');

        return Inertia::render('SalesBook/Edit', [
            'book' => $salesBook,
            'categories' => SalesBookCategory::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreSalesBookRequest $request, SalesBook $salesBook)
    {
        $validated = $request->validated();

        $salesBook->update([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'status' => $validated['status'],
            'order_index' => $validated['order_index'] ?? $salesBook->order_index,
        ]);

        // Обновляем категории
        $salesBook->categories()->sync($validated['category_ids'] ?? []);

        return redirect()->route('sales-book.books.show', $salesBook)
            ->with('success', 'Книга успешно обновлена');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesBook $salesBook)
    {
        $salesBook->delete();

        return redirect()->route('sales-book.index')
            ->with('success', 'Книга успешно удалена');
    }
}
