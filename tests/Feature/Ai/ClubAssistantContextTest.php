<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Enums\SourceGroup;
use App\Ai\Agents\ClubAssistant;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Providers\Tools\FileSearch;

function fileSearchOf(iterable $tools): ?FileSearch
{
    foreach ($tools as $tool) {
        if ($tool instanceof FileSearch) {
            return $tool;
        }
    }

    return null;
}

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

it('names the roles after the BI report keys and keeps the old ones as aliases', function (): void {
    expect(RoleName::RESTAURANT_MANAGER->value)->toBe('restaurant_manager')
        ->and(RoleName::FUTBOL_MANAGER->label())->toBe('Gerencia de fútbol')
        ->and(RoleName::INCIDENCES_MANAGER->label())->toBe('Gerencia de incidencias')
        ->and(RoleName::GUESTS_MANAGER->label())->toBe('Gerencia de invitados')
        ->and(RoleName::WELLNESS_MANAGER->label())->toBe('Gerencia de bienestar')
        ->and(RoleName::RESTAURANT_CAPTAIN->value)->toBe('restaurant_captain');
});

it('narrows single-area roles to their source groups and leaves the rest open', function (): void {
    expect(RoleName::ADMIN->sourceGroups())->toBe([])
        ->and(RoleName::GENERAL_SERVICE_MANAGER->sourceGroups())->toBe([])
        ->and(RoleName::GOLF_MANAGER->sourceGroups())->toBe(['golf'])
        ->and(RoleName::RESTAURANT_MANAGER->sourceGroups())->toBe(['restaurant'])
        ->and(RoleName::RESTAURANT_CAPTAIN->sourceGroups())->toBe(['restaurant'])
        ->and(RoleName::FUTBOL_MANAGER->sourceGroups())->toBe(['futbol'])
        ->and(RoleName::INCIDENCES_MANAGER->sourceGroups())->toBe(['incidences'])
        ->and(RoleName::GUESTS_MANAGER->sourceGroups())->toBe(['guests'])
        ->and(RoleName::WELLNESS_MANAGER->sourceGroups())->toBe(['wellness'])
        ->and(RoleName::AESTHETICS_MANAGER->sourceGroups())->toBe(['wellness', 'aesthetic'])
        ->and(RoleName::MASSAGE_MANAGER->sourceGroups())->toBe(['wellness', 'massage'])
        ->and(RoleName::PODIATRY_MANAGER->sourceGroups())->toBe(['wellness']);
});

it('labels the wellness and BI groups in Spanish', function (): void {
    expect(SourceGroup::labelFor('wellness'))->toBe('Bienestar')
        ->and(SourceGroup::labelFor('futbol'))->toBe('Fútbol')
        ->and(SourceGroup::labelFor('incidences'))->toBe('Incidencias')
        ->and(SourceGroup::labelFor('guests'))->toBe('Invitados');
});

it('searches the store of the user club and narrows a single-area role to its groups', function (): void {
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm', 'vallealto' => 'vs_va']]);

    $search = fileSearchOf(ClubAssistant::make(club: ClubName::VALLEALTO, role: RoleName::MASSAGE_MANAGER)->tools());

    expect($search->ids())->toBe(['vs_va'])
        ->and($search->filters)->toBe([['type' => 'in', 'key' => 'group', 'value' => ['wellness', 'massage']]]);
});

it('does not filter by group for the admin', function (): void {
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm']]);

    $search = fileSearchOf(ClubAssistant::make(club: ClubName::CCM, role: RoleName::ADMIN)->tools());

    expect($search->ids())->toBe(['vs_ccm'])
        ->and($search->filters)->toBe([]);
});

it('degrades to web search only when the club has no vector store configured', function (): void {
    config(['services.openai.vector_stores' => []]);

    $tools = iterator_to_array(ClubAssistant::make(club: ClubName::VALLEALTO, role: RoleName::ADMIN)->tools());

    expect(fileSearchOf($tools))->toBeNull()
        ->and($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(WebSearch::class);
});

it('offers no file search when the user has no club', function (): void {
    $tools = iterator_to_array(ClubAssistant::make()->tools());

    expect(fileSearchOf($tools))->toBeNull()
        ->and($tools[0])->toBeInstanceOf(WebSearch::class);
});
