<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;

Route::get('/', function () {
    return to_route('chats.index');
})->name('home');

Route::resource('chat', ChatController::class)
    ->names('chats')
    ->except(['create', 'edit'])
    ->middlewareFor(['store', 'update', 'destroy'], ['auth', 'verified']);

Route::post('/chat/stream/{chat}', ChatStreamController::class)
    ->name('chat.stream')
    ->middleware(['auth', 'verified']);

Route::get('test', function (){
    $projects = config('services.softok2mds.projects', []);
    $fileNames = config('services.softok2mds.files', []);
    foreach ($projects as $project) {
        foreach ($fileNames as $fileName) {
            $path = $project . '/' . $fileName;
            $response = Http::retry(3, 100)
                ->withBasicAuth(config('services.softok2mds.username'), config('services.softok2mds.password'))
                ->get(config('services.softok2mds.base_url') . $path . '.md');

            dd($response->body());

        }
    }

    return response()->json(['projects' => $projects, 'files' => $fileNames]);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
