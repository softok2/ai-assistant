<?php

declare(strict_types=1);

namespace App\Ai\Files;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Lo que el SDK no expone: listar lo que hay de verdad en la cuenta de OpenAI
 * (archivos con purpose "assistants") y en el vector store del club.
 */
final class OpenAiFileInventory
{
    /**
     * @return Collection<int, array{id: string, filename: string, bytes: int, created_at: int}>
     */
    public function accountFiles(): Collection
    {
        return $this->paginate('files', ['purpose' => 'assistants'])
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
    public function storeFiles(): Collection
    {
        $storeId = config('services.openai.vector_store_id');

        return $this->paginate("vector_stores/{$storeId}/files")
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
    private function paginate(string $path, array $query = []): Collection
    {
        $items = collect();
        $after = null;

        do {
            $page = $this->client()
                ->get($path, array_filter($query + ['limit' => 100, 'after' => $after]))
                ->throw()
                ->json();

            $data = collect($page['data'] ?? []);
            $items = $items->concat($data);
            $after = ($page['has_more'] ?? false) ? ($page['last_id'] ?? $data->last()['id'] ?? null) : null;
        } while ($after !== null);

        return $items->values();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai.providers.openai.url'), '/'))
            ->withToken((string) config('ai.providers.openai.key'))
            ->acceptJson()
            ->timeout(30);
    }
}
