<?php

declare(strict_types=1);

namespace App\Ai\Sources;

use App\Enums\ClubName;
use InvalidArgumentException;
use App\Ai\Sources\Drivers\PentahoMdsSource;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

/**
 * Construye la fuente de cada club a partir de `config/knowledge.php`. Un club
 * sin entrada no tiene fuente (devuelve null): el import lo salta.
 */
final class KnowledgeSourceFactory
{
    public function for(ClubName $club): ?KnowledgeSource
    {
        $config = config("knowledge.sources.{$club->value}");

        if (! is_array($config)) {
            return null;
        }

        $driver = $config['driver'] ?? null;

        return match ($driver) {
            'pentaho' => new PentahoMdsSource(
                baseUrl: (string) $config['base_url'],
                username: (string) $config['username'],
                password: (string) $config['password'],
                files: array_values(array_filter((array) ($config['files'] ?? []))),
                project: $club->value,
            ),
            'bi_knowledge' => new BiKnowledgeManifestSource(
                baseUrl: (string) $config['base_url'],
                username: (string) $config['username'],
                password: (string) $config['password'],
            ),
            default => throw new InvalidArgumentException('Driver de fuente desconocido ['.($driver ?? 'null').'] para el club ['.$club->value.'].'),
        };
    }

    /**
     * Clubes con fuente configurada, en el orden del mapa.
     *
     * @return array<int, ClubName>
     */
    public function clubs(): array
    {
        return collect((array) config('knowledge.sources'))
            ->keys()
            ->map(fn (string $club) => ClubName::tryFrom($club))
            ->filter()
            ->values()
            ->all();
    }
}
