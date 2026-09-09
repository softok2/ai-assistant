<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Ai\Agents\ClubAssistant;

it('labels every club and role in Spanish', function (): void {
    expect(ClubName::CCM->label())->toBe('Club Campestre Monterrey')
        ->and(ClubName::VALLEALTO->label())->toBe('Club Valle Alto')
        ->and(ClubName::TERRALTA->label())->toBe('Club Terralta')
        ->and(ClubName::HERRADURA->label())->toBe('Club La Herradura')
        ->and(ClubName::SALTILLO->label())->toBe('Club Campestre Saltillo');

    expect(RoleName::ADMIN->label())->toBe('Dirección general')
        ->and(RoleName::GOLF_MANAGER->label())->toBe('Gerencia de golf')
        ->and(RoleName::TENNIS_MANAGER->label())->toBe('Gerencia de tenis')
        ->and(RoleName::PADDLE_MANAGER->label())->toBe('Gerencia de pádel')
        ->and(RoleName::RESTAURANT_CAPTAIN->label())->toBe('Capitanía de restaurante')
        ->and(RoleName::AESTHETICS_MANAGER->label())->toBe('Gerencia de estética')
        ->and(RoleName::MASSAGE_MANAGER->label())->toBe('Gerencia de masajes')
        ->and(RoleName::PODIATRY_MANAGER->label())->toBe('Gerencia de podología')
        ->and(RoleName::GENERAL_SERVICE_MANAGER->label())->toBe('Gerencia de servicios generales');
});

it('gives the admin every area and each manager only its own', function (): void {
    expect(RoleName::GOLF_MANAGER->areas())->toBe(['Golf'])
        ->and(RoleName::TENNIS_MANAGER->areas())->toBe(['Tenis'])
        ->and(RoleName::PADDLE_MANAGER->areas())->toBe(['Pádel'])
        ->and(RoleName::ADMIN->areas())->toContain('Golf')
        ->and(RoleName::ADMIN->areas())->toContain('Restaurantes')
        ->and(count(RoleName::ADMIN->areas()))->toBeGreaterThan(5);
});

it('composes the instructions with the club, the role, its areas and the user name', function (): void {
    $instructions = ClubAssistant::make(
        club: ClubName::VALLEALTO,
        role: RoleName::GOLF_MANAGER,
        userName: 'Ana Gómez',
    )->instructions();

    expect($instructions)->toContain('Club Valle Alto')
        ->and($instructions)->toContain('Gerencia de golf')
        ->and($instructions)->toContain('Golf')
        ->and($instructions)->toContain('Ana Gómez')
        ->and($instructions)->not->toContain('{{club}}')
        ->and($instructions)->not->toContain('{{role}}');
});

it('falls back to neutral wording when no club or role is known', function (): void {
    $instructions = ClubAssistant::make()->instructions();

    expect($instructions)->not->toContain('{{club}}')
        ->and($instructions)->not->toContain('{{role}}')
        ->and($instructions)->toContain('el club');
});

it('keeps the base prompt free of hard-coded club and role', function (): void {
    $prompt = file_get_contents(resource_path('prompts/club_assistant.md'));

    expect($prompt)->not->toContain('Campestre Monterrey')
        ->and($prompt)->not->toContain('Director General')
        ->and($prompt)->toContain('{{club}}')
        ->and($prompt)->toContain('{{role}}');
});
