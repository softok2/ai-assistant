<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires only the active pentaho rows of the club and dispatches the purge', function (): void {
    Bus::fake();
    $pentaho = File::factory()->forClub(ClubName::CCM)->completed()->create();
    $manifest = File::factory()->forClub(ClubName::CCM)->fromManifest('golf-live.md')->completed()->create();
    $other = File::factory()->forClub(ClubName::VALLEALTO)->completed()->create();

    $this->artisan('assistant-files:retire-origin', ['club' => 'ccm', 'origin' => 'pentaho'])->assertSuccessful();

    expect($pentaho->fresh()->expired_at)->not->toBeNull()
        ->and($manifest->fresh()->expired_at)->toBeNull()
        ->and($other->fresh()->expired_at)->toBeNull();
    Bus::assertDispatched(RemoveExpiredDocs::class, fn (RemoveExpiredDocs $job): bool => $job->project === 'ccm');
});

it('changes nothing on a dry run', function (): void {
    Bus::fake();
    $pentaho = File::factory()->forClub(ClubName::CCM)->completed()->create();

    $this->artisan('assistant-files:retire-origin', ['club' => 'ccm', 'origin' => 'pentaho', '--dry-run' => true])->assertSuccessful();

    expect($pentaho->fresh()->expired_at)->toBeNull();
    Bus::assertNotDispatched(RemoveExpiredDocs::class);
});

it('rejects an unknown club or origin', function (): void {
    $this->artisan('assistant-files:retire-origin', ['club' => 'nope', 'origin' => 'pentaho'])->assertFailed();
    $this->artisan('assistant-files:retire-origin', ['club' => 'ccm', 'origin' => 'nope'])->assertFailed();
});
