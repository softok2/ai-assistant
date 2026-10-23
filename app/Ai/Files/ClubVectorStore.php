<?php

declare(strict_types=1);

namespace App\Ai\Files;

use Throwable;
use Laravel\Ai\Store;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use App\Models\VectorStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Único punto que sabe qué vector store de OpenAI pertenece a cada club. El id
 * vive en la tabla `vector_stores`, con una fila por club, entorno y proveedor:
 * así cada entorno tiene el suyo y ningún camino de código puede tocar un store
 * sin decir de qué club es. Si falta, `idFor` adopta el store del proyecto que
 * ya tenga el nombre `{club}-assistant-{entorno}` y la descripción exacta que
 * pone esta app (la fila se perdió, p. ej. por un `migrate:fresh`) y solo si
 * no hay ninguno lo crea en OpenAI.
 */
final readonly class ClubVectorStore
{
    public function __construct(
        private ClubAiProvider $providers,
        private OpenAiStoreDirectory $directory,
    ) {}

    /**
     * Id del store del club en este entorno; si no hay fila adopta el store del
     * proyecto con el nombre y la descripción de este entorno o, si no existe,
     * lo crea en OpenAI.
     */
    public function idFor(ClubName $club): string
    {
        $existing = $this->existingIdFor($club);

        if ($existing !== null) {
            return $existing;
        }

        $provider = $this->providerFor($club);
        $environment = app()->environment();

        return Cache::lock("club-vector-store:{$club->value}:{$environment}:{$provider}", 60)->block(10, function () use ($club, $provider, $environment): string {
            $existing = $this->existingIdFor($club);

            if ($existing !== null) {
                return $existing;
            }

            $name = "{$club->value}-assistant-{$environment}";
            $description = "Fuentes del asistente · club={$club->value} environment={$environment} provider={$provider}";
            $adopted = $this->directory->findId($club, $name, $description);
            $storeId = $adopted ?? Stores::create($name, $description, [], null, $provider)->id;

            try {
                VectorStore::query()->create([
                    'club' => $club->value,
                    'environment' => $environment,
                    'provider' => $provider,
                    'store_id' => $storeId,
                    'name' => $name,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otro proceso ganó la carrera: nos quedamos con su fila y, si
                // acabamos de crear uno, soltamos el store sobrante.
                if ($adopted === null) {
                    $this->dropExtraStore($club, $environment, $provider, $storeId);
                }

                return (string) $this->existingIdFor($club);
            }

            return $storeId;
        });
    }

    public function storeFor(ClubName $club): Store
    {
        return Stores::get($this->idFor($club), $this->providers->nameFor($club));
    }

    /**
     * Solo lectura: nunca crea nada en OpenAI.
     */
    public function existingIdFor(ClubName $club): ?string
    {
        return VectorStore::query()
            ->where([
                'club' => $club->value,
                'environment' => app()->environment(),
                'provider' => $this->providerFor($club),
            ])
            ->value('store_id');
    }

    public function existingStoreFor(ClubName $club): ?Store
    {
        $id = $this->existingIdFor($club);

        return $id === null ? null : Stores::get($id, $this->providers->nameFor($club));
    }

    /**
     * Mejor esfuerzo: si el borrado falla queda un store huérfano en OpenAI, y el
     * log dice cuál es para poder borrarlo a mano.
     */
    private function dropExtraStore(ClubName $club, string $environment, string $provider, string $storeId): void
    {
        $context = ['club' => $club->value, 'environment' => $environment, 'provider' => $provider, 'store_id' => $storeId];

        try {
            if (! Stores::delete($storeId, $provider)) {
                Log::warning("Could not delete the extra vector store {$storeId} after losing the creation race", $context);
            }
        } catch (Throwable $throwable) {
            Log::warning("Could not delete the extra vector store {$storeId} after losing the creation race", $context + ['error' => $throwable->getMessage()]);
        }
    }

    private function providerFor(ClubName $club): string
    {
        return $this->providers->nameFor($club) ?? 'openai';
    }
}
