<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Tipo de archivo que el usuario adjuntó a un chat, para agrupar la Biblioteca
 * sin exponer el mime crudo.
 */
enum AttachmentKind: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Spreadsheet = 'spreadsheet';
    case Document = 'document';
    case Other = 'other';

    /**
     * El mime manda; cuando llega vacío o genérico se cae a la extensión del
     * nombre original, que es lo que guarda `messages.attachments`.
     */
    public static function fromMime(?string $mime, ?string $name = null): self
    {
        $mime = mb_strtolower((string) $mime);

        $byMime = match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            $mime === 'application/pdf' => self::Pdf,
            in_array($mime, [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true) => self::Spreadsheet,
            in_array($mime, [
                'text/plain',
                'text/markdown',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true) => self::Document,
            default => null,
        };

        return $byMime ?? self::fromExtension($name);
    }

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagen',
            self::Pdf => 'PDF',
            self::Spreadsheet => 'Hoja de cálculo',
            self::Document => 'Documento',
            self::Other => 'Otro',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Image => 'Imágenes',
            self::Pdf => 'PDF',
            self::Spreadsheet => 'Hojas de cálculo',
            self::Document => 'Documentos',
            self::Other => 'Otros',
        };
    }

    private static function fromExtension(?string $name): self
    {
        $extension = mb_strtolower(Str::afterLast((string) $name, '.'));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => self::Image,
            'pdf' => self::Pdf,
            'csv', 'xls', 'xlsx' => self::Spreadsheet,
            'doc', 'docx', 'txt', 'md' => self::Document,
            default => self::Other,
        };
    }
}
