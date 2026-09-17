<?php

declare(strict_types=1);

namespace App\Ai\Files;

use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Lo que el SDK no expone: listar lo que hay de verdad en la cuenta de OpenAI
 * (archivos con purpose "assistants") y en el vector store del club. Cada
 * club habla con el proyecto de OpenAI de su clave.
 */
final class OpenAiFileInventory
{
    /**
     * El SDK sube con purpose "user_data"; los archivos del asistente viejo
     * (Assistants API) quedaron con "assistants". Hay que mirar los dos.
     */
    private const PURPOSES = ['user_data', 'assistants'];

    public function __construct(
        private readonly ClubVectorStore $stores,
        private readonly ClubAiProvider $providers,
    ) {}

    /**
     * @return Collection<int, array{id: string, filename: string, bytes: int, created_at: int}>
     */
    public function accountFiles(ClubName $club): Collection
    {
        return collect(self::PURPOSES)
            ->flatMap(fn (string $purpose) => $this->paginate($club, 'files', ['purpose' => $purpose]))
            ->unique('id')
            ->values()
            ->map(fn (array $file) => [
                'id' => $file['id'],
                'filename' => $file['filename'] ?? '',
                'bytes' => (int) ($file['bytes'] ?? 0),
                'created_at' => (int) ($file['created_at'] ?? 0),
            ]);
    }

    /**
     * @return Collection<int, array{id: string, created_at: int, status: string, environment: ?string}>
     */
    public function storeFiles(ClubName $club): Collection
    {
        $storeId = $this->stores->idFor($club);

        return $this->paginate($club, "vector_stores/{$storeId}/files")
            ->map(fn (array $file) => [
                'id' => $file['id'],
                'created_at' => (int) ($file['created_at'] ?? 0),
                'status' => (string) ($file['status'] ?? ''),
                'environment' => isset($file['attributes']['environment']) ? (string) $file['attributes']['environment'] : null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function paginate(ClubName $club, string $path, array $query = []): Collection
    {
        $items = collect();
        $after = null;

        do {
            $page = $this->client($club)
                ->get($path, array_filter($query + ['limit' => 100, 'after' => $after]))
                ->throw()
                ->json();

            $data = collect($page['data'] ?? []);
            $items = $items->concat($data);
            $after = ($page['has_more'] ?? false) ? ($page['last_id'] ?? $data->last()['id'] ?? null) : null;
        } while ($after !== null);

        return $items->values();
    }

    private function client(ClubName $club): PendingRequest
    {
        return Http::baseUrl(rtrim($this->providers->urlFor($club), '/'))
            ->withToken($this->providers->keyFor($club))
            ->acceptJson()
            ->timeout(30);
    }
}
