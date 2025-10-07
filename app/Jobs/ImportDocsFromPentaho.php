<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

final class ImportDocsFromPentaho implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function handle(): void
    {
        $projects = config('services.softok2mds.projects', []);
        $fileNames = config('services.softok2mds.files', []);

        foreach ($projects as $project) {
            foreach ($fileNames as $fileName) {
                $path = $project.'/'.$fileName;
                $response = Http::retry(3, 100)
                    ->withBasicAuth(config('services.softok2mds.username'), config('services.softok2mds.password'))
                    ->get(config('services.softok2mds.base_url').$path.'.md');

                if ($response->failed()) {
                    Log::error("Failed to fetch document $path from Pentaho: HTTP {$response->status()}");

                    continue;
                }

                DB::transaction(function () use ($response, $path) {
                    $media = Media::fromPentaho($path.'-'.time().'.md', $response->body());
                    Media::markAsExpired($media->refresh());
                });
            }
        }
    }

    /** * Handle a job failure. */
    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to fetch document: '.$exception->getMessage(), $exception->getTrace() ?? []);
    }
}
