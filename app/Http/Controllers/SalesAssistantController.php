<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SalesAssistantController extends Controller
{
    public function book(): Response
    {
        return Inertia::render('SalesAssistant/Book');
    }

    public function trainer(): Response
    {
        return Inertia::render('SalesAssistant/Trainer');
    }
}
