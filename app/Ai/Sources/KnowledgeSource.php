<?php

declare(strict_types=1);

namespace App\Ai\Sources;

/**
 * De dónde saca el asistente los documentos de un club. Cada club tiene un
 * driver (Pentaho de transición, manifiesto de `bi:knowledge`); el import no
 * sabe cuál es.
 */
interface KnowledgeSource
{
    /**
     * Documentos disponibles ahora mismo. Un documento que no se pudo leer se
     * registra y se omite: nunca aborta el resto.
     *
     * @return iterable<int, RemoteDocument>
     */
    public function documents(): iterable;
}
