<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesAssistantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('SalesAssistant/Index', [
            'title' => 'Помощник продавца',
            'description' => 'Инструменты для подготовки и ведения продаж',
            'activeKey' => 'sales-assistant',
        ]);
    }
}
