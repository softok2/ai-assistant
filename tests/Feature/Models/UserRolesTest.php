<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Enums\RoleName;
use Illuminate\Support\Facades\DB;

it('reads the roles once no matter how many times they are asked for', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['name' => 'golf_manager']));

    DB::enableQueryLog();

    expect($user->primaryRole())->toBe(RoleName::GOLF_MANAGER)
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->primaryRole())->toBe(RoleName::GOLF_MANAGER);

    expect(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('still recognises an administrator', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['name' => 'admin']));

    expect($user->isAdmin())->toBeTrue()
        ->and($user->primaryRole())->toBe(RoleName::ADMIN);
});

it('reports no role for a user without one', function (): void {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse()
        ->and($user->primaryRole())->toBeNull();
});

it('ignores a role name that the application does not know', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['name' => 'rol_inventado']));

    expect($user->primaryRole())->toBeNull()
        ->and($user->isAdmin())->toBeFalse();
});
