<?php

declare(strict_types=1);

namespace App\Ai\Sources;

use Closure;
use App\Enums\SourceOrigin;

/**
 * Un documento tal como lo describe su fuente. `content` es perezoso: el
 * manifiesto del BI trae el checksum sin el cuerpo, y solo se descarga lo que
 * de verdad cambió.
 */
final readonly class RemoteDocument
{
    /**
     * @param  string  $name  nombre de archivo SIN carpeta de club (`golf-live.md`)
     * @param  string  $checksum  sha256 hex del contenido
     * @param  Closure(): string  $content
     */
    public function __construct(
        public string $name,
        public string $group,
        public string $checksum,
        public SourceOrigin $origin,
        private Closure $content,
    ) {}

    public function content(): string
    {
        return ($this->content)();
    }
}
