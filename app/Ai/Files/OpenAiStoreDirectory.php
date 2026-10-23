<?php

declare(strict_types=1);

namespace App\Ai\Files;

use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Listados crudos de la API de OpenAI que el SDK no expone, con la clave del
 * club: recorre la paginación (`has_more` / `after`) y lista los vector stores
 * del proyecto. No depende de `ClubVectorStore`, así este puede usarlo para
 * adoptar un store por nombre sin crear un ciclo.
 */
final readonly class OpenAiStoreDirectory
{
    public function __construct(private ClubAiProvider $providers) {}

    /**
     * Todos los vector stores del proyecto de OpenAI del club.
     *
     * @return Collection<int, array{id: string, name: string, description: ?string}>
     */
    public function stores(ClubName $club): Collection
    {
        return $this->paginate($club, 'vector_stores')
            ->map(fn (array $store): array => [
                'id' => (string) $store['id'],
                'name' => (string) ($store['name'] ?? ''),
                // El SDK guarda la descripción en `metadata.description`; OpenAI no
                // tiene campo `description` en el objeto vector store.
                'description' => isset($store['metadata']['description']) ? (string) $store['metadata']['description'] : null,
            ]);
    }

    /**
     * Id del store del proyecto con ese nombre Y esa descripción exactos (la de
     * `metadata.description`, que es donde la escribe `Stores::create`), o null.
     * Exigir los dos evita adoptar un store con el mismo nombre que no creó
     * esta app (p. ej. el viejo `ccm-assistant-local` compartido).
     */
    public function findId(ClubName $club, string $name, string $description): ?string
    {
        return $this->stores($club)
            ->first(fn (array $store): bool => $store['name'] === $name && $store['description'] === $description)['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return Collection<int, array<string, mixed>>
     */
    public function paginate(ClubName $club, string $path, array $query = []): Collection
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
        return Http::baseUrl(mb_rtrim($this->providers->urlFor($club), '/'))
            ->withToken($this->providers->keyFor($club))
            ->acceptJson()
            ->timeout(30);
    }
}
