<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;

final class ImportDocsFromPentaho
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $fileNames = [
            'golf-output.md',
        ];

        try {

            foreach ($fileNames as $fileName) {
                $response = Http::retry(3, 100)
                    ->withBasicAuth('mds_ccm', 'Ccm.c0m$')
                    ->get('https://ai-mds.softok2.com/ccm/'.$fileNames[0]);

                if ($response->failed()) {
                    Log::error('Failed to fetch document: '.$response->status(), $response->json() ?? []);

                    return;
                }

                Media::addFromPentaho($fileNames[0], $response->body());
            }

        } catch (Throwable $e) {
            ds($e->getMessage());
            report($e);

            Log::error('ConnectionException: '.$e->getMessage());

            return;
        }

    }
}
