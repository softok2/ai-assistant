<?php

declare(strict_types=1);

namespace App\Ai\Files;

use RuntimeException;
use App\Enums\ClubName;

final class MissingClubVectorStore extends RuntimeException
{
    public static function forClub(ClubName $club): self
    {
        return new self("No hay vector store configurado para el club [{$club->value}] (services.openai.vector_stores).");
    }
}
