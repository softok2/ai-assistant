<?php

declare(strict_types=1);

use App\Models\File;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Support\Facades\Bus;

it('queues the purge of the expired documents', function () {
    Bus::fake();
    File::factory()->completed()->expired()->create();

    $this->actingAs(adminUser())
        ->delete(route('sources.expired.purge'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Bus::assertDispatched(RemoveExpiredDocs::class);
});
