<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return to_route('chats.index');
})->middleware('auth.external')->name('home');

Route::resource('chat', ChatController::class)
    ->names('chats')
    ->except(['create', 'edit'])
    ->middleware(['auth', 'verified']);

Route::post('/chat/stream/{chat}', ChatStreamController::class)
    ->name('chat.stream')
    ->middleware(['auth', 'verified']);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
