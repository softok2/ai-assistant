<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\User;
use App\Enums\ClubName;
use App\Ai\Sources\KnowledgeSourceFactory;

/**
 * Qué club ve la pantalla de Fuentes. Un admin externo tiene club y no lo
 * cambia; un admin local sin club elige, y por defecto ve el primero con
 * fuente configurada.
 */
final class ResolveSourcesClubAction
{
    public function __construct(private readonly KnowledgeSourceFactory $sources) {}

    public function execute(?User $user, ?string $requested): ClubName
    {
        $own = $user?->clubName();

        if ($own !== null) {
            return $own;
        }

        $requestedClub = is_string($requested) ? ClubName::tryFrom($requested) : null;

        return $requestedClub ?? $this->sources->clubs()[0] ?? ClubName::CCM;
    }

    public function isLocked(?User $user): bool
    {
        return $user?->clubName() !== null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (ClubName $club) => ['value' => $club->value, 'label' => $club->label()], $this->sources->clubs());
    }
}
