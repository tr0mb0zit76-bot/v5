<?php

namespace App\Http\Controllers;

use App\Models\SalesBook;
use App\Models\SalesBookCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesBookIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $books = SalesBook::with(['author', 'categories'])
            ->withCount('pages')
            ->latest()
            ->get();

        $categories = SalesBookCategory::orderBy('name')->get();

        return Inertia::render('SalesBook/Index', [
            'books' => $books,
            'categories' => $categories,
        ]);
    }
}
