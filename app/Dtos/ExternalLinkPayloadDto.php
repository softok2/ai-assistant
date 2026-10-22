<?php

declare(strict_types=1);

namespace App\Dtos;

final readonly class ExternalLinkPayloadDto
{
    public function __construct(
        private string $club,
        private string $externalId,
        private string $userName,
        private string $role,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            club: $data['club'],
            externalId: (string) $data['user_id'],
            userName: $data['user_name'],
            role: $data['role'],
        );
    }

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
}
