<?php

declare(strict_types=1);

use App\Jobs\SyncLock;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

it('forbids the page to a regular user', function () {
    File::factory()->completed()->create(['group' => 'golf']);

    $this->actingAs(User::factory()->create())
        ->get(route('sources'))
        ->assertForbidden();
});

it('gives an admin the expired files, the groups and the sync state', function () {
    File::factory()->completed()->create(['group' => 'golf']);
    File::factory()->completed()->create(['group' => 'tennis']);
    File::factory()->completed()->expired()->create(['group' => 'golf']);

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Sources')
            ->has('files', 2)
            ->has('expiredFiles', 1)
            ->where('expiredFiles.0.expired_at', fn ($value) => $value !== null)
            ->where('groups', ['golf', 'tennis'])
            ->where('syncRunning', false)
            ->has('chatHistory')
            ->has('health'));
});

it('labels the group and tells apart a pentaho report from a manual upload', function () {
    File::factory()->completed()->create(['group' => 'paddle', 'name' => 'ccm/paddle-output-1787767203.md']);
    File::factory()->completed()->create(['group' => 'unknown-area', 'name' => 'unknown-area-manual-1787767203-ab12cd.pdf']);

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('files.0.group_label', 'Pádel')
            ->where('files.0.origin', 'pentaho')
            ->where('files.1.group_label', 'Unknown Area')
            ->where('files.1.origin', 'manual'));
});

it('summarises the health of the sources', function () {
    config(['services.openai.vector_store_id' => 'vs_abcdefgh12345678']);

    File::factory()->completed()->create(['group' => 'golf']);
    File::factory()->create(['group' => 'tennis']);
    File::factory()->completed()->expired()->create(['group' => 'golf']);

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('health.indexed', 1)
            ->where('health.total', 2)
            ->where('health.expired_count', 1)
            ->where('health.environment', 'testing')
            ->where('health.vector_store_suffix', '12345678')
            ->where('health.latest_sync_at', fn ($value) => $value !== null)
            ->where('health.schedule.every', 'Cada 2 h')
            ->where('health.schedule.window', 'De 08:00 a 22:00')
            ->has('health.schedule.next_run_at'));
});

it('computes the next sync from the configured cadence, skipping the night', function (string $now, string $expected) {
    Carbon::setTestNow(Carbon::parse($now, config('app.timezone')));

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('health.schedule.next_run_at', fn ($value) => Carbon::parse($value)
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i') === $expected));
})->with([
    'a media tarde' => ['2026-09-09 12:37', '2026-09-09 14:00'],
    'de noche, tras la ventana' => ['2026-09-09 23:10', '2026-09-10 08:00'],
    'justo antes de abrir' => ['2026-09-10 07:59', '2026-09-10 08:00'],
]);

it('tells the admin when a sync is already running', function () {
    Cache::lock(SyncLock::KEY, 60)->get();

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('syncRunning', true));
});

it('does not keep the lock it checked', function () {
    $this->actingAs(adminUser())->get(route('sources'))->assertOk();

    expect(Cache::lock(SyncLock::KEY, 60)->get())->toBeTrue();
});

it('forbids every management route to a user who is not an admin', function () {
    $file = File::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('sources.sync'))->assertForbidden();
    $this->get(route('sources.reconcile.report'))->assertForbidden();
    $this->post(route('sources.reconcile.apply'))->assertForbidden();
    $this->post(route('sources.files.store'))->assertForbidden();
    $this->post(route('sources.files.reindex', $file))->assertForbidden();
    $this->delete(route('sources.files.destroy', $file))->assertForbidden();
    $this->delete(route('sources.expired.purge'))->assertForbidden();
});

it('hands the flash message of an action to the next page', function () {
    Bus::fake();
    $this->actingAs(adminUser());

    $this->post(route('sources.sync'));

    $this->get(route('sources'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.success', 'Sincronización iniciada en segundo plano.'));
});
