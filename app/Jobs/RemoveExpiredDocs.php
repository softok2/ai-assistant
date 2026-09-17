<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

final class RemoveExpiredDocs implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $project = null) {}

    /**
     * Sin esto, `ShouldBeUniqueUntilProcessing` usaría la clase como llave y
     * la purga de un club bloquearía la de los demás.
     */
    public function uniqueId(): string
    {
        return $this->project ?? 'all';
    }

    public function handle(): void
    {
        File::expired()
            ->when($this->project !== null, fn ($query) => $query->where('project', $this->project))
            ->lazy()
            ->each
            ->remove();
    }
}
