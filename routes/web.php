<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;
use App\Http\Controllers\ChatTranscriptionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return to_route('chats.index');
})->middleware('auth.external')->name('home');

Route::get('/chat/{chat}', [ChatController::class, 'show'])
    ->name('chats.show');

Route::resource('chat', ChatController::class)
    ->names('chats')
    ->except(['create', 'edit', 'show'])
    ->middleware(['auth', 'verified']);

Route::get('/library', App\Http\Controllers\LibraryController::class)
    ->name('library')
    ->middleware(['auth', 'verified']);

Route::post('/chat-suggestions/{chat}', App\Http\Controllers\ChatSuggestionsController::class)
    ->name('chat.suggestions')
    ->middleware(['auth', 'verified']);

Route::post('/chat/stream/{chat}', ChatStreamController::class)
    ->name('chat.stream')
    ->middleware(['auth', 'verified']);

Route::post('/chat-attachments', [ChatAttachmentController::class, 'store'])
    ->name('chat.attachments.store')
    ->middleware(['auth', 'verified']);

Route::get('/chat-attachments/{path}', [ChatAttachmentController::class, 'show'])
    ->where('path', '.*')
    ->name('chat.attachments.show')
    ->middleware(['auth', 'verified']);

Route::post('/chat-transcribe', ChatTranscriptionController::class)
    ->name('chat.transcribe')
    ->middleware(['auth', 'verified']);

Route::post('/chat-speech/{message}', App\Http\Controllers\ChatSpeechController::class)
    ->name('chat.speech')
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/reportes/configuracion', [App\Http\Controllers\ReportSettingController::class, 'edit'])
        ->name('reports.settings.edit');
    Route::put('/reportes/configuracion', [App\Http\Controllers\ReportSettingController::class, 'update'])
        ->name('reports.settings.update');
    Route::post('/reportes/prueba', [App\Http\Controllers\ReportSettingController::class, 'test'])
        ->name('reports.settings.test');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
