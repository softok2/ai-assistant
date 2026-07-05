<?php

declare(strict_types=1);

namespace App\Dtos;

final class ExternalLinkPayloadDto
{
    public function __construct(
        private readonly string $club,
        private readonly string $externalId,
        private readonly string $userName,
        private readonly string $role,
    ) {}

    public function getClub(): string
    {
        return $this->club;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            club: $data['club'],
            externalId: (string) $data['user_id'],
            userName: $data['user_name'],
            role: $data['role'],
        );
    }
}
