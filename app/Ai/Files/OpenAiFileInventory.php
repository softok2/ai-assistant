<?php

declare(strict_types=1);

namespace App\Ai\Files;

use App\Enums\ClubName;
use Illuminate\Support\Collection;

/**
 * Lo que el SDK no expone: listar lo que hay de verdad en la cuenta de OpenAI
 * (archivos con purpose "assistants"), en el vector store del club en este
 * entorno y en todos los stores del proyecto. Cada club habla con el proyecto
 * de OpenAI de su clave.
 */
final readonly class OpenAiFileInventory
{
    /**
     * El SDK sube con purpose "user_data"; los archivos del asistente viejo
     * (Assistants API) quedaron con "assistants". Hay que mirar los dos.
     */
    private const array PURPOSES = ['user_data', 'assistants'];

    public function __construct(
        private ClubVectorStore $stores,
        private OpenAiStoreDirectory $directory,
    ) {}

    /**
     * @return Collection<int, array{id: string, filename: string, bytes: int, created_at: int}>
     */
    public function accountFiles(ClubName $club): Collection
    {
        return collect(self::PURPOSES)
            ->flatMap(fn (string $purpose): Collection => $this->directory->paginate($club, 'files', ['purpose' => $purpose]))
            ->unique('id')
            ->values()
            ->map(fn (array $file): array => [
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
        $storeId = $this->stores->existingIdFor($club);

        if ($storeId === null) {
            return collect();
        }

        return $this->directory->paginate($club, "vector_stores/{$storeId}/files")
            ->map(fn (array $file): array => [
                'id' => $file['id'],
                'created_at' => (int) ($file['created_at'] ?? 0),
                'status' => (string) ($file['status'] ?? ''),
                'environment' => isset($file['attributes']['environment']) ? (string) $file['attributes']['environment'] : null,
            ]);
    }

    /**
     * Ids de todos los archivos adjuntos a algún vector store del proyecto, de
     * cualquier entorno: los entornos comparten proyecto, así que un archivo de
     * la cuenta que está aquí tiene dueño aunque no sea de este entorno.
     *
     * @return Collection<string, true>
     */
    public function attachedFileIds(ClubName $club): Collection
    {
        return $this->directory->stores($club)
            ->flatMap(fn (array $store): Collection => $this->directory->paginate($club, "vector_stores/{$store['id']}/files")->pluck('id'))
            ->mapWithKeys(fn (string $id): array => [$id => true]);
    }
}
