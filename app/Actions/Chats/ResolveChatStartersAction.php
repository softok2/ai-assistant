<?php

declare(strict_types=1);

namespace App\Actions\Chats;

use Throwable;
use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Dtos\LibrarySnapshot;
use App\Ai\Starters\DefaultStarters;
use Illuminate\Support\Facades\Cache;
use App\Ai\Agents\ChatStarterSuggester;

/**
 * Las cuatro preguntas de arranque de la pantalla de inicio. Solo se cachea el
 * resultado bueno: si el modelo falla, el respaldo estático se sirve sin
 * guardarse, para volver a intentarlo en la siguiente visita.
 */
final class ResolveChatStartersAction
{
    private const TTL_HOURS = 6;

    private const MAX_DOCUMENTS = 25;

    /**
     * @return array<int, array{area: string, question: string}>
     */
    public function execute(?ClubName $club, ?RoleName $role, LibrarySnapshot $library): array
    {
        $fallback = DefaultStarters::for($role);

        if ($library->isEmpty()) {
            return $fallback;
        }

        $key = $this->cacheKey($club, $role);
        $cached = Cache::get($key);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $starters = $this->suggest($club, $role, $library);

        if ($starters === null) {
            return $fallback;
        }

        Cache::put($key, $starters, now()->addHours(self::TTL_HOURS));

        return $starters;
    }

    /**
     * @return array<int, array{area: string, question: string}>|null null cuando no se pudo obtener una propuesta completa
     */
    private function suggest(?ClubName $club, ?RoleName $role, LibrarySnapshot $library): ?array
    {
        try {
            $response = (new ChatStarterSuggester($club, $role))->prompt($this->documentList($library));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $starters = collect($response->structured['starters'] ?? [])
            ->filter(fn ($starter): bool => is_array($starter)
                && is_string($starter['area'] ?? null)
                && is_string($starter['question'] ?? null)
                && trim($starter['question']) !== '')
            ->map(fn (array $starter): array => [
                'area' => trim($starter['area']),
                'question' => trim($starter['question']),
            ])
            ->take(4)
            ->values()
            ->all();

        return count($starters) === 4 ? $starters : null;
    }

    private function documentList(LibrarySnapshot $library): string
    {
        $lines = collect($library->documents)
            ->take(self::MAX_DOCUMENTS)
            ->map(fn (array $document): string => '- '.$document['name']
                .' (área: '.$document['group'].', actualizado: '.($document['synced_at'] ?? 'sin fecha').')')
            ->all();

        return "Documentos indexados en la biblioteca del club:\n".implode("\n", $lines);
    }

    private function cacheKey(?ClubName $club, ?RoleName $role): string
    {
        return 'chat-starters:'.($club?->value ?? 'sin-club').':'.($role?->value ?? 'sin-rol');
    }
}
