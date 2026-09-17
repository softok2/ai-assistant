<?php

declare(strict_types=1);

use App\Models\File;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Support\Facades\Bus;

it('queues the purge of the expired documents', function () {
    Bus::fake();
    File::factory()->completed()->expired()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.expired.purge'), ['club' => 'ccm'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Bus::assertDispatched(RemoveExpiredDocs::class);
});

it('purges only the expired rows of the requested club', function () {
    Bus::fake();

    $this->actingAs(adminUser())->delete(route('sources.expired.purge'), ['club' => 'vallealto']);

    Bus::assertDispatched(RemoveExpiredDocs::class, fn (RemoveExpiredDocs $job) => $job->project === 'vallealto');
});

it('purges the default club when none is given', function () {
    Bus::fake();

    $this->actingAs(adminUser())
        ->delete(route('sources.expired.purge'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Bus::assertDispatched(RemoveExpiredDocs::class);
});

/**
 * Antes un `club` inválido caía en el club por defecto en silencio; ahora
 * falla la validación, igual que en reconciliar y subir documentos.
 */
it('rejects an unknown club instead of silently purging the default one', function () {
    Bus::fake();

    $this->actingAs(adminUser())
        ->delete(route('sources.expired.purge'), ['club' => 'marte'])
        ->assertSessionHasErrors('club');

    Bus::assertNotDispatched(RemoveExpiredDocs::class);
});
