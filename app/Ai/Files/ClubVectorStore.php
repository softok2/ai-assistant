<?php

declare(strict_types=1);

namespace App\Ai\Files;

use Laravel\Ai\Store;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Ai\ClubAiProvider;

/**
 * Único punto que sabe qué vector store de OpenAI pertenece a cada club. Nadie
 * más lee `services.openai.vector_stores`: así ningún camino de código puede
 * tocar un store sin decir de qué club es.
 */
final class ClubVectorStore
{
    public function __construct(private readonly ClubAiProvider $providers) {}

    public function idFor(ClubName $club): string
    {
        $id = config("services.openai.vector_stores.{$club->value}");

        if (! is_string($id) || trim($id) === '') {
            throw MissingClubVectorStore::forClub($club);
        }

        return $id;
    }

    public function storeFor(ClubName $club): Store
    {
        return Stores::get($this->idFor($club), $this->providers->nameFor($club));
    }

    /**
     * Clubes con store configurado, en el orden del mapa.
     *
     * @return array<int, ClubName>
     */
    public function configured(): array
    {
        return collect((array) config('services.openai.vector_stores'))
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->keys()
            ->map(fn (string $club) => ClubName::tryFrom($club))
            ->filter()
            ->values()
            ->all();
    }
}
