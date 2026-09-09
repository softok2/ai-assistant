<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ChatSpeechController;
use App\Http\Controllers\ChatStreamController;
use App\Http\Controllers\ReportSettingController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatMessagePdfController;
use App\Http\Controllers\ChatSuggestionsController;
use App\Http\Controllers\ChatTranscriptionController;
use App\Http\Controllers\LibraryManagementController;

Route::get('/', function () {
    return to_route('chats.index');
})->middleware('auth.external')->name('home');

Route::get('/chat/{chat}', [ChatController::class, 'show'])
    ->name('chats.show');

Route::get('/chats/search', [ChatController::class, 'search'])
    ->name('chats.search')
    ->middleware(['auth', 'verified']);

Route::resource('chat', ChatController::class)
    ->names('chats')
    ->except(['create', 'edit', 'show'])
    ->middleware(['auth', 'verified']);

Route::get('/library', LibraryController::class)
    ->name('library')
    ->middleware(['auth', 'verified']);

Route::post('/chat-suggestions/{chat}', ChatSuggestionsController::class)
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

Route::post('/chat-speech/{message}', ChatSpeechController::class)
    ->name('chat.speech')
    ->middleware(['auth', 'verified']);

// Cada PDF levanta un Chromium: se limita para que nadie tumbe el servidor
// pulsando el botón.
Route::post('/chat/messages/{message}/pdf', ChatMessagePdfController::class)
    ->name('chat.messages.pdf')
    ->middleware(['auth', 'verified', 'throttle:10,1']);

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::post('/library/sync', [LibraryManagementController::class, 'sync'])
        ->name('library.sync');
    Route::get('/library/reconcile', [LibraryManagementController::class, 'reconcileReport'])
        ->name('library.reconcile.report');
    Route::post('/library/reconcile', [LibraryManagementController::class, 'reconcile'])
        ->name('library.reconcile.apply');
    Route::post('/library/files', [LibraryManagementController::class, 'store'])
        ->name('library.files.store');
    Route::post('/library/files/{file}/reindex', [LibraryManagementController::class, 'reindex'])
        ->name('library.files.reindex');
    Route::delete('/library/files/{file}', [LibraryManagementController::class, 'destroy'])
        ->name('library.files.destroy');
    Route::delete('/library/expired', [LibraryManagementController::class, 'purgeExpired'])
        ->name('library.expired.purge');

    Route::get('/reportes/configuracion', [ReportSettingController::class, 'edit'])
        ->name('reports.settings.edit');
    Route::put('/reportes/configuracion', [ReportSettingController::class, 'update'])
        ->name('reports.settings.update');
    Route::post('/reportes/prueba', [ReportSettingController::class, 'test'])
        ->name('reports.settings.test');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
