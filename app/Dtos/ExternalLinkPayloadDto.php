<?php

declare(strict_types=1);

namespace App\Dtos;

final class ExternalLinkPayloadDto
{
    public function __construct(
        private readonly string $club,
        private readonly string $userName,
        private readonly int $roleId,
        private readonly string $token
    ) {}

    public function getClub(): string
    {
        return $this->club;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getRoleId(): int
    {
        return $this->roleId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            club: $data['club'],
            userName: $data['user_name'],
            roleId: (int) $data['role_id'],
            token: $data['token']
        );
    }
}
