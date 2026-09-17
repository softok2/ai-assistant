<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

final class MissingOpenAiKey extends RuntimeException
{
    public static function forProvider(string $name): self
    {
        return new self("El proveedor [{$name}] no tiene clave de OpenAI (OPENAI_API_KEY[_CLUB] en el .env).");
    }
}
