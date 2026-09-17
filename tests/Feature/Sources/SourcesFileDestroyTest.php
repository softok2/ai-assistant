<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    Files::fake();
    config(['services.openai.vector_stores.ccm' => 'vs_test']);
});

it('deletes an active document from OpenAI, the disk and the database', function () {
    $file = File::factory()->forClub(ClubName::CCM)->completed()->create();
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file), ['club' => 'ccm'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(File::find($file->id))->toBeNull();
    Storage::assertMissing('docs/'.$file->name);
});

it('deletes an expired document too', function () {
    $file = File::factory()->forClub(ClubName::CCM)->completed()->expired()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file), ['club' => 'ccm'])
        ->assertRedirect();

    expect(File::find($file->id))->toBeNull();
});

it('warns when OpenAI refuses to delete and keeps the row for the next cleanup', function () {
    Stores::fake(fn () => throw new RuntimeException('OpenAI is down'));
    $file = File::factory()->forClub(ClubName::CCM)->completed()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file), ['club' => 'ccm'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(File::find($file->id))->not->toBeNull();
});

it('refuses to delete a file that belongs to another club', function () {
    $admin = adminUser();
    $admin->forceFill(['club_name' => 'vallealto'])->save();
    $file = File::factory()->forClub(ClubName::CCM)->completed()->create();
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs($admin)
        ->delete(route('sources.files.destroy', $file), ['club' => 'ccm'])
        ->assertNotFound();

    expect(File::find($file->id))->not->toBeNull();
    Storage::assertExists('docs/'.$file->name);
});
