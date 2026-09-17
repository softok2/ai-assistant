<?php

declare(strict_types=1);

namespace App\Ai\Sources\Drivers;

use Throwable;
use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use App\Ai\Sources\KnowledgeSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Manifiesto que publica `bi:knowledge` en el panel del club:
 * `GET {base_url}/manifest.json` describe cada documento con su sha256, y
 * `GET {base_url}/{name}` sirve el markdown. Solo se baja lo que el import
 * decide que cambió.
 */
final class BiKnowledgeManifestSource implements KnowledgeSource
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function documents(): iterable
    {
        try {
            $manifest = $this->client()->get($this->url('manifest.json'))->throw()->json();
        } catch (Throwable $e) {
            Log::error("BI knowledge: no se pudo leer el manifiesto de {$this->baseUrl}: ".$e->getMessage());

            return;
        }

        foreach ((array) ($manifest['documents'] ?? []) as $entry) {
            if (! isset($entry['name'], $entry['group'], $entry['checksum'])) {
                Log::warning('BI knowledge: entrada del manifiesto incompleta', ['entry' => $entry]);

                continue;
            }

            $name = (string) $entry['name'];

            if (! $this->isSafeName($name)) {
                Log::warning('BI knowledge: nombre de documento inválido, se omite', ['name' => $name]);

                continue;
            }

            yield new RemoteDocument(
                name: $name,
                group: (string) $entry['group'],
                checksum: (string) $entry['checksum'],
                origin: SourceOrigin::BiKnowledge,
                content: fn (): string => $this->client()->get($this->url($name))->throw()->body(),
            );
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.$path;
    }

    /**
     * `name` termina en `Storage::put('docs/{club}/{name}')`: no puede traer
     * `..`, empezar con `/` ni tener subcarpetas, solo un `.md` plano.
     */
    private function isSafeName(string $name): bool
    {
        if (str_contains($name, '..') || str_starts_with($name, '/')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._-]+\.md$/', $name);
    }

    private function client(): PendingRequest
    {
        return Http::retry(3, 100)
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->timeout(30);
    }
}
