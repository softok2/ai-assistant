<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    Files::fake();
    config(['services.openai.vector_store_id' => 'vs_test']);
});

it('deletes an active document from OpenAI, the disk and the database', function () {
    $file = File::factory()->completed()->create();
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(File::find($file->id))->toBeNull();
    Storage::assertMissing('docs/'.$file->name);
});

it('deletes an expired document too', function () {
    $file = File::factory()->completed()->expired()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file))
        ->assertRedirect();

    expect(File::find($file->id))->toBeNull();
});

it('warns when OpenAI refuses to delete and keeps the row for the next cleanup', function () {
    Stores::fake(fn () => throw new RuntimeException('OpenAI is down'));
    $file = File::factory()->completed()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.files.destroy', $file))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(File::find($file->id))->not->toBeNull();
});
