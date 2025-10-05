<?php

declare(strict_types=1);

use App\Models\Media;
use Illuminate\Support\Facades\DB;
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


            DB::transaction(function () use ($response, $path) {
                $media = Media::fromPentaho($path.'-'.time().'.md', $response->body());
                Media::markAsExpired($media->refresh());
            });

        }
    }

    return response()->json(['projects' => $projects, 'files' => $fileNames]);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
