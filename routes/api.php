<?php

use App\Http\Controllers\MessengerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('mobile/messenger')
    ->name('mobile.messenger.')
    ->group(function (): void {
        Route::get('/unread-count', [MessengerController::class, 'unreadCount'])->name('unread-count');
        Route::get('/colleagues', [MessengerController::class, 'colleagues'])->name('colleagues');
        Route::get('/document-chips', [MessengerController::class, 'documentChips'])->name('document-chips');
        Route::get('/conversations', [MessengerController::class, 'conversations'])->name('conversations.index');
        Route::post('/conversations/open', [MessengerController::class, 'openDirect'])->name('conversations.open');
        Route::post('/conversations/groups', [MessengerController::class, 'storeGroup'])->name('conversations.groups.store');
        Route::get('/conversations/{conversation}/messages', [MessengerController::class, 'messages'])->name('conversations.messages');
        Route::post('/conversations/{conversation}/messages', [MessengerController::class, 'storeMessage'])->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/read', [MessengerController::class, 'markRead'])->name('conversations.read');
    });
