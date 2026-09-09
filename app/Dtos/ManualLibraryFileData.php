<?php

declare(strict_types=1);

namespace App\Dtos;

use Illuminate\Http\UploadedFile;

/**
 * Documento que un administrador sube a mano a la biblioteca, fuera del
 * import automático de Pentaho.
 */
final readonly class ManualLibraryFileData
{
    public function __construct(
        public string $group,
        public UploadedFile $file,
    ) {}

    /**
     * @param  array{group: string, file: UploadedFile}  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            group: $data['group'],
            file: $data['file'],
        );
    }
}
