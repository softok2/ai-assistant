<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Enums\SourceOrigin;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm', 'vallealto' => 'vs_va']]);
});

it('knows its club from the project column', function () {
    expect(File::factory()->forClub(ClubName::VALLEALTO)->make()->club())->toBe(ClubName::VALLEALTO);
});

it('uploads to the store of its own club', function () {
    $file = File::factory()->forClub(ClubName::VALLEALTO)->create();
    Storage::put('docs/'.$file->name, '# va');

    $file->upload();

    Files::assertStored(fn ($stored) => true);
    expect($file->fresh()->status->value)->toBe('completed');
});

it('expires only older siblings of the same club, group and name', function () {
    $otherClub = File::factory()->forClub(ClubName::CCM)->fromManifest('golf-live.md')->completed()->create();
    $otherName = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create();
    $older = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create();
    $newer = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create();

    $newer->expireSiblings();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($otherName->fresh()->expired_at)->toBeNull()
        ->and($otherClub->fresh()->expired_at)->toBeNull();
});

it('expires pentaho siblings by club and group because their name changes every run', function () {
    $older = File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-100.md']);
    $otherClub = File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf', 'name' => 'vallealto/golf-output-100.md']);
    $newer = File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-200.md']);

    $newer->expireSiblings();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($otherClub->fresh()->expired_at)->toBeNull()
        ->and($newer->origin)->toBe(SourceOrigin::Pentaho);
});
