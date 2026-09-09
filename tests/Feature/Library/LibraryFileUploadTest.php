<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\MediaStatus;
use App\Jobs\UploadAssistantDoc;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Bus::fake();
});

it('stores a manual document and queues its upload', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => UploadedFile::fake()->create('reglamento.md', 12),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $file = File::query()->sole();

    expect($file->project)->toBe('manual')
        ->and($file->group)->toBe('golf')
        ->and($file->status)->toBe(MediaStatus::PENDING)
        ->and($file->name)->toStartWith('golf-manual-')
        ->and($file->name)->toEndWith('.md')
        ->and($file->expired_at)->toBeNull();

    Storage::assertExists('docs/'.$file->name);
    Bus::assertDispatched(UploadAssistantDoc::class, fn (UploadAssistantDoc $job) => $job->fileId === $file->id);
});

it('rejects a file type the assistant cannot read', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => UploadedFile::fake()->create('hoja.xlsx', 12),
        ])
        ->assertSessionHasErrors('file');

    expect(File::query()->count())->toBe(0);
    Bus::assertNotDispatched(UploadAssistantDoc::class);
});

it('rejects a document over ten megabytes', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => UploadedFile::fake()->create('enorme.pdf', 10_241),
        ])
        ->assertSessionHasErrors('file');

    expect(File::query()->count())->toBe(0);
});

it('rejects a group that is not a lowercase slug', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'Golf Pro',
            'file' => UploadedFile::fake()->create('reglamento.md', 12),
        ])
        ->assertSessionHasErrors('group');

    expect(File::query()->count())->toBe(0);
});

it('requires both the group and the file', function () {
    $this->actingAs(adminUser())
        ->postJson(route('library.files.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['group', 'file']);
});

/**
 * `mimes:` valida el tipo real, no el nombre del cliente: un archivo de texto
 * llamado `notas.html` pasa la validación y antes se guardaba en disco con
 * extensión .html. Ahora la extensión sale del tipo detectado.
 */
it('stores a text file with a safe extension whatever the client called it', function () {
    $path = tempnam(sys_get_temp_dir(), 'notas').'.html';
    file_put_contents($path, 'Texto plano del reglamento del club.');

    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => new UploadedFile($path, 'notas.html', 'text/plain', null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(File::query()->sole()->name)->toEndWith('.txt');
});

/** Una subida con extensión .php se rechaza en la validación. */
it('rejects an upload that carries a php extension', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => UploadedFile::fake()->createWithContent('notas.php', 'texto plano'),
        ])
        ->assertSessionHasErrors('file');

    expect(File::query()->count())->toBe(0);
});

it('keeps the markdown extension for a markdown document', function () {
    $this->actingAs(adminUser())
        ->post(route('library.files.store'), [
            'group' => 'golf',
            'file' => UploadedFile::fake()->createWithContent('reglamento.md', '# titulo'),
        ])
        ->assertRedirect();

    expect(File::query()->sole()->name)->toEndWith('.md');
});

it('does not collide when two documents land in the same second', function () {
    $this->freezeTime();
    $this->actingAs(adminUser());

    $this->post(route('library.files.store'), [
        'group' => 'golf',
        'file' => UploadedFile::fake()->create('uno.md', 4),
    ])->assertRedirect();

    $this->post(route('library.files.store'), [
        'group' => 'golf',
        'file' => UploadedFile::fake()->create('dos.md', 4),
    ])->assertRedirect();

    $names = File::query()->pluck('name');

    expect($names)->toHaveCount(2)
        ->and($names->unique())->toHaveCount(2);
    Storage::assertExists('docs/'.$names[0]);
    Storage::assertExists('docs/'.$names[1]);
});
