<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\User;
use App\Jobs\SyncLock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

it('does not expose the management props to a regular user', function () {
    File::factory()->completed()->create(['group' => 'golf']);
    File::factory()->completed()->expired()->create(['group' => 'golf']);

    $this->actingAs(User::factory()->create())
        ->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library')
            ->has('files', 1)
            ->missing('expiredFiles')
            ->missing('canManage')
            ->missing('syncRunning'));
});

it('gives an admin the expired files, the groups and the sync state', function () {
    File::factory()->completed()->create(['group' => 'golf']);
    File::factory()->completed()->create(['group' => 'tennis']);
    File::factory()->completed()->expired()->create(['group' => 'golf']);

    $this->actingAs(adminUser())
        ->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library')
            ->where('canManage', true)
            ->has('files', 2)
            ->has('expiredFiles', 1)
            ->where('expiredFiles.0.expired_at', fn ($value) => $value !== null)
            ->where('groups', ['golf', 'tennis'])
            ->where('syncRunning', false));
});

it('tells the admin when a sync is already running', function () {
    Cache::lock(SyncLock::KEY, 60)->get();

    $this->actingAs(adminUser())
        ->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('syncRunning', true));
});

it('does not keep the lock it checked', function () {
    $this->actingAs(adminUser())->get(route('library'))->assertOk();

    expect(Cache::lock(SyncLock::KEY, 60)->get())->toBeTrue();
});

it('forbids every management route to a user who is not an admin', function () {
    $file = File::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('library.sync'))->assertForbidden();
    $this->get(route('library.reconcile.report'))->assertForbidden();
    $this->post(route('library.reconcile.apply'))->assertForbidden();
    $this->post(route('library.files.store'))->assertForbidden();
    $this->post(route('library.files.reindex', $file))->assertForbidden();
    $this->delete(route('library.files.destroy', $file))->assertForbidden();
    $this->delete(route('library.expired.purge'))->assertForbidden();
});

it('hands the flash message of an action to the next page', function () {
    Bus::fake();
    $this->actingAs(adminUser());

    $this->post(route('library.sync'));

    $this->get(route('library'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.success', 'Sincronización iniciada en segundo plano.'));
});
