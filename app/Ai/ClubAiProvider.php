<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\ClubName;

/**
 * Qué proveedor de OpenAI usa cada club. Cada club tiene su proyecto en
 * OpenAI (uso y costo separados, y sus stores solo los ve su clave); un club
 * sin clave propia usa el proveedor por defecto y nunca falla.
 */
final class ClubAiProvider
{
    /**
     * Nombre del proveedor en `config/ai.php`, o null para el de por defecto.
     */
    public function nameFor(?ClubName $club): ?string
    {
        if ($club === null) {
            return null;
        }

        $name = 'openai_'.$club->value;
        $key = config("ai.providers.{$name}.key");

        return is_string($key) && trim($key) !== '' ? $name : null;
    }

    /**
     * Clave con la que hablar con la API por HTTP directo (inventario).
     *
     * @throws MissingOpenAiKey si el proveedor resuelto no tiene clave.
     */
    public function keyFor(?ClubName $club): string
    {
        $name = $this->nameFor($club) ?? 'openai';
        $key = trim((string) config("ai.providers.{$name}.key"));

        if ($key === '') {
            throw MissingOpenAiKey::forProvider($name);
        }

        return $key;
    }

    /**
     * URL base del mismo proveedor del que sale la clave: token y URL nunca
     * pueden venir de proyectos distintos.
     */
    public function urlFor(?ClubName $club): string
    {
        $name = $this->nameFor($club) ?? 'openai';
        $url = trim((string) config("ai.providers.{$name}.url"));

        return $url !== '' ? $url : 'https://api.openai.com/v1';
    }
}
