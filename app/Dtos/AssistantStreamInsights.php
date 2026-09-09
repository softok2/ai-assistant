<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Lo que el asistente hizo durante una respuesta (herramientas que usó) y de
 * dónde salió la información (documentos de la biblioteca y páginas web).
 */
final class AssistantStreamInsights
{
    /**
     * @param  array<int, array{type: string, label: string, status: string}>  $activity
     * @param  array<int, array{kind: string, title: string, url: ?string, document_name: ?string, synced_at: ?string}>  $sources
     */
    public function __construct(
        public readonly array $activity = [],
        public readonly array $sources = [],
    ) {}

    /**
     * Forma que se guarda dentro de `messages.parts`. Las claves vacías no se
     * guardan para que un mensaje sin herramientas quede igual que antes.
     *
     * @return array<string, mixed>
     */
    public function toParts(): array
    {
        return array_filter([
            'activity' => $this->activity,
            'sources' => $this->sources,
        ], fn (array $value): bool => $value !== []);
    }
}
