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
use App\Http\Controllers\AssistantSourcesController;
use App\Http\Controllers\ChatTranscriptionController;
use App\Http\Controllers\AssistantSourcesManagementController;

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

Route::get('/biblioteca', LibraryController::class)
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
    Route::get('/fuentes', AssistantSourcesController::class)
        ->name('sources');
    Route::post('/fuentes/sync', [AssistantSourcesManagementController::class, 'sync'])
        ->name('sources.sync');
    Route::get('/fuentes/reconcile', [AssistantSourcesManagementController::class, 'reconcileReport'])
        ->name('sources.reconcile.report');
    Route::post('/fuentes/reconcile', [AssistantSourcesManagementController::class, 'reconcile'])
        ->name('sources.reconcile.apply');
    Route::post('/fuentes/files', [AssistantSourcesManagementController::class, 'store'])
        ->name('sources.files.store');
    Route::post('/fuentes/files/{file}/reindex', [AssistantSourcesManagementController::class, 'reindex'])
        ->name('sources.files.reindex');
    Route::delete('/fuentes/files/{file}', [AssistantSourcesManagementController::class, 'destroy'])
        ->name('sources.files.destroy');
    Route::delete('/fuentes/expired', [AssistantSourcesManagementController::class, 'purgeExpired'])
        ->name('sources.expired.purge');

    Route::get('/reportes/configuracion', [ReportSettingController::class, 'edit'])
        ->name('reports.settings.edit');
    Route::put('/reportes/configuracion', [ReportSettingController::class, 'update'])
        ->name('reports.settings.update');
    Route::post('/reportes/prueba', [ReportSettingController::class, 'test'])
        ->name('reports.settings.test');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
