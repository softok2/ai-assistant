<?php

declare(strict_types=1);

namespace App\Ai\Sources\Drivers;

use Throwable;
use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use App\Ai\Sources\KnowledgeSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Driver de transición: los reportes `.md` del MDS de Pentaho. El MDS no
 * publica checksum, así que se baja el cuerpo y se calcula aquí; el nombre
 * lleva timestamp porque el MDS siempre sirve el mismo nombre.
 */
final class PentahoMdsSource implements KnowledgeSource
{
    /**
     * @param  array<int, string>  $files  nombres sin extensión (`golf-output`)
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly array $files,
        private readonly string $project,
    ) {}

    public function documents(): iterable
    {
        foreach ($this->files as $fileName) {
            $path = $this->project.'/'.$fileName;

            try {
                $body = Http::retry(3, 100)
                    ->withBasicAuth($this->username, $this->password)
                    ->get($this->baseUrl.$path.'.md')
                    ->throw()
                    ->body();
            } catch (Throwable $e) {
                Log::error("Pentaho: no se pudo bajar {$path}: ".$e->getMessage());

                continue;
            }

            yield new RemoteDocument(
                name: $fileName.'-'.time().'.md',
                group: (string) str($fileName)->before('-'),
                checksum: hash('sha256', $body),
                origin: SourceOrigin::Pentaho,
                content: fn (): string => $body,
            );
        }
    }
}
