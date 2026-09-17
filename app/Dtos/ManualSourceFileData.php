<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Enums\ClubName;
use Illuminate\Http\UploadedFile;

/**
 * Documento que un administrador sube a mano a la biblioteca de un club,
 * fuera del import automático.
 */
final readonly class ManualSourceFileData
{
    public function __construct(
        public ClubName $club,
        public string $group,
        public UploadedFile $file,
    ) {}

    /**
     * @param  array{club: string, group: string, file: UploadedFile}  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            club: ClubName::from($data['club']),
            group: $data['group'],
            file: $data['file'],
        );
    }
}
