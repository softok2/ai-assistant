<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde salió un documento de `files`. Decide cómo se caducan los hermanos
 * y qué etiqueta lleva en Fuentes.
 */
enum SourceOrigin: string
{
    case Pentaho = 'pentaho';
    case BiKnowledge = 'bi_knowledge';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Pentaho => 'Pentaho',
            self::BiKnowledge => 'BI del club',
            self::Manual => 'Manual',
        };
    }
}
