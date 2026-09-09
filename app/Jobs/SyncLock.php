<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Candado del sync de documentos. Dura lo que puede durar una ingesta lenta
 * (antes 5 minutos: la siguiente corrida del scheduler arrancaba encima de la
 * anterior y ambas subían los mismos pendientes).
 */
final class SyncLock
{
    public const KEY = 'syncing-assistant-files';

    public const SECONDS = 1800;

    /**
     * El sondeo toma el candado un instante para saber si estaba libre; con un
     * TTL corto una petición que muera antes de soltarlo no bloquea el sync
     * media hora.
     */
    public const PROBE_SECONDS = 1;

    public static function acquire(): ?Lock
    {
        $lock = Cache::lock(self::KEY, self::SECONDS);

        return $lock->get() ? $lock : null;
    }

    /**
     * Comprueba si hay una sincronización en curso sin quedarse el candado: lo
     * toma con un TTL de un segundo, lo suelta enseguida y responde que nadie
     * lo tenía.
     */
    public static function isHeld(): bool
    {
        $lock = Cache::lock(self::KEY, self::PROBE_SECONDS);

        if (! $lock->get()) {
            return true;
        }

        $lock->release();

        return false;
    }

    public static function release(): void
    {
        Cache::lock(self::KEY)->forceRelease();
    }
}
