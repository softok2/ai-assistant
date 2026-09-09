<?php

declare(strict_types=1);

namespace App\Reports\Contracts;

use App\Models\Message;

/**
 * Convierte una respuesta del asistente en un PDF. Vive detrás de un contrato
 * para poder sustituir el motor de render (hoy Chromium) y para poder fingirlo
 * en las pruebas sin abrir un navegador.
 */
interface RendersMessageAsPdf
{
    public function render(Message $message): string;

    /** Nombre del archivo que se descarga. */
    public function filename(Message $message): string;
}
