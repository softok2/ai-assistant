<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

final class ImportDocsFromPentaho implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $projects = config('services.softok2mds.projects', []);
        $fileNames = config('services.softok2mds.files', []);

        foreach ($projects as $project) {
            foreach ($fileNames as $fileName) {
                $path = $project.'/'.$fileName;

                try {
                    $response = Http::retry(3, 100)
                        ->withBasicAuth(config('services.softok2mds.username'), config('services.softok2mds.password'))
                        ->get(config('services.softok2mds.base_url').$path.'.md');

                    DB::transaction(function () use ($response, $path) {
                        $media = File::fromPentaho($path.'-'.time().'.md', $response->body());
                        File::markAsExpired($media->refresh());
                    });
                } catch (Throwable $e) {
                    Log::error('Error fetching document '.$path.': '.$e->getMessage(), $e->getTrace() ?? []);

                    continue;
                }
            }
        }
    }

    /** * Handle a job failure. */
    public function failed(?Throwable $exception): void
    {
        Cache::lock('syncing-assistant-files')->forceRelease();

        Log::error('Failed to fetch document: '.$exception->getMessage(), $exception->getTrace() ?? []);
    }
}
