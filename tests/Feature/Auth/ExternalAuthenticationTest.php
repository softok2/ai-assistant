<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

function signedLinkParams(array $overrides = []): array
{
    $params = array_merge([
        'club' => 'ccm',
        'user_id' => 42,
        'user_name' => 'Juan Pérez',
        'role' => 'golf_manager',
        'issued_at' => now()->getTimestamp(),
    ], $overrides);

    $params['sig'] ??= hash_hmac(
        'sha256',
        implode('|', [$params['club'], $params['user_id'], $params['user_name'], $params['role'], $params['issued_at']]),
        config('app.club_signature_secrets.ccm'),
    );

    return $params;
}

beforeEach(function (): void {
    Config::set('app.club_signature_secrets.ccm', 'test-secret-rotated');
    Role::firstOrCreate(['name' => 'golf_manager']);
    Role::firstOrCreate(['name' => 'admin']);
});

it('authenticates a fully signed link and regenerates the session', function (): void {
    $this->get('/?'.http_build_query(signedLinkParams()))
        ->assertRedirect(route('chats.index'));

    $this->assertAuthenticated();

    $user = User::sole();
    expect($user->external_id)->toBe('42');
    expect($user->club_name)->toBe('ccm');
    expect($user->roles()->sole()->name)->toBe('golf_manager');
});

it('keeps the same account when the display name changes', function (): void {
    $this->get('/?'.http_build_query(signedLinkParams()));
    auth()->logout();
    $this->get('/?'.http_build_query(signedLinkParams(['user_name' => 'Juan P. Actualizado'])));

    expect(User::count())->toBe(1);
    expect(User::sole()->name)->toBe('Juan P. Actualizado');
});

it('rejects an expired link', function (): void {
    $this->get('/?'.http_build_query(signedLinkParams(['issued_at' => now()->subMinutes(10)->getTimestamp()])))
        ->assertForbidden();

    $this->assertGuest();
});

it('rejects a tampered role', function (): void {
    $params = signedLinkParams();
    $params['role'] = 'admin';

    $this->get('/?'.http_build_query($params))->assertForbidden();
    $this->assertGuest();
});

it('rejects a tampered user id', function (): void {
    $params = signedLinkParams();
    $params['user_id'] = 999;

    $this->get('/?'.http_build_query($params))->assertForbidden();
});

it('rejects the legacy token-only format', function (): void {
    $this->get('/?'.http_build_query([
        'club' => 'ccm',
        'user_name' => 'Juan Pérez',
        'role_id' => 1,
        'token' => hash_hmac('sha256', 'ccm|Juan Pérez', 'test-secret-rotated'),
    ]))->assertStatus(400);
});

it('rejects everything when the club secret is missing', function (): void {
    Config::set('app.club_signature_secrets.ccm', null);

    $this->get('/?'.http_build_query(signedLinkParams(['sig' => 'cualquiera'])))
        ->assertForbidden();
});

it('sends the frame-ancestors policy', function (): void {
    Config::set('app.frame_ancestors', 'https://ccm-admin-api.test');

    $response = $this->get('/?'.http_build_query(signedLinkParams()));

    expect($response->headers->get('Content-Security-Policy'))
        ->toBe("frame-ancestors 'self' https://ccm-admin-api.test");
});
