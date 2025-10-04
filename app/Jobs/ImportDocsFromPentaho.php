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
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;

final class ImportDocsFromPentaho
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function handle(): void
    {
        $fileNames = [
            'golf-output',
        ];

        DB::beginTransaction();

        try {
            foreach ($fileNames as $fileName) {
                $response = Http::retry(3, 100)
                    ->withBasicAuth('mds_ccm', 'Ccm.c0m$')
                    ->get('https://ai-mds.softok2.com/ccm/'.$fileName.'.md');

                if ($response->failed()) {
                    Log::error('Failed to fetch document: '.$response->status(), $response->json() ?? []);

                    return;
                }

                $media = Media::fromPentaho($fileName.'-'.time().'.md', $response->body());
                Media::markAsExpired($media->refresh());

                DB::commit();
            }

        } catch (Throwable $e) {
            dd($e->getMessage());
            DB::rollBack();
            report($e);
            Log::error('ConnectionException: '.$e->getMessage());

            return;
        }

    }
}
