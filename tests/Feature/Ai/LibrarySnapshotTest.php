<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Queries\LibrarySnapshotQuery;

/**
 * El contador de "Fuentes" del chat debe decir lo mismo que el asistente puede
 * citar: los documentos indexados de SU club, y solo las áreas de su rol.
 */
it('counts only the indexed documents of the club', function (): void {
    File::factory()->forClub(ClubName::CCM)->completed()->count(8)->create();
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->count(2)->create();

    $snapshot = app(LibrarySnapshotQuery::class)->execute(ClubName::VALLEALTO);

    expect($snapshot->documentCount)->toBe(2);
});

it('counts nothing without a club because the assistant has no store to search', function (): void {
    File::factory()->forClub(ClubName::CCM)->completed()->count(8)->create();

    $snapshot = app(LibrarySnapshotQuery::class)->execute(null);

    expect($snapshot->documentCount)->toBe(0);
});

it('narrows the count to the areas the role may search', function (): void {
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf']);
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'restaurant']);

    $snapshot = app(LibrarySnapshotQuery::class)->execute(ClubName::VALLEALTO, RoleName::GOLF_MANAGER);

    expect($snapshot->documentCount)->toBe(1);
});

it('lists the documents of the club only', function (): void {
    File::factory()->forClub(ClubName::CCM)->completed()->count(3)->create();
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf']);

    $snapshot = app(LibrarySnapshotQuery::class)->execute(ClubName::VALLEALTO, withDocuments: true);

    expect($snapshot->documents)->toHaveCount(1)
        ->and($snapshot->documents[0]['group'])->toBe('golf');
});
