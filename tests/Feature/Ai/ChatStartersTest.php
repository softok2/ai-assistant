<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Dtos\LibrarySnapshot;
use Illuminate\Support\Carbon;
use App\Queries\LibrarySnapshotQuery;
use Illuminate\Support\Facades\Cache;
use App\Ai\Agents\ChatStarterSuggester;
use App\Actions\Chats\ResolveChatStartersAction;

function library(): LibrarySnapshot
{
    return new LibrarySnapshot(
        syncedAt: Carbon::parse('2026-09-01 10:00:00'),
        documentCount: 2,
        documents: [
            ['name' => 'golf-semana.md', 'group' => 'golf', 'synced_at' => '2026-09-01T10:00:00.000000Z'],
            ['name' => 'restaurant-semana.md', 'group' => 'restaurant', 'synced_at' => '2026-08-30T10:00:00.000000Z'],
        ],
    );
}

function fakeStarters(int $count): void
{
    ChatStarterSuggester::fake([[
        'starters' => array_map(
            fn (int $i): array => ['area' => 'Golf', 'question' => 'Pregunta '.$i],
            range(1, $count)
        ),
    ]]);
}

it('returns the four starters proposed by the agent', function (): void {
    fakeStarters(4);

    $starters = app(ResolveChatStartersAction::class)
        ->execute(ClubName::CCM, RoleName::GOLF_MANAGER, library());

    expect($starters)->toHaveCount(4)
        ->and($starters[0])->toBe(['area' => 'Golf', 'question' => 'Pregunta 1']);
});

it('falls back to the static starters when the agent fails', function (): void {
    ChatStarterSuggester::fake(fn () => throw new RuntimeException('sin cuota'));

    $starters = app(ResolveChatStartersAction::class)
        ->execute(ClubName::CCM, RoleName::TENNIS_MANAGER, library());

    expect($starters)->toHaveCount(4)
        ->and($starters[0]['area'])->toBe('Tenis')
        ->and(Cache::has('chat-starters:ccm:tennis_manager'))->toBeFalse();
});

it('asks the agent again on the next visit after a failure', function (): void {
    ChatStarterSuggester::fake(fn () => throw new RuntimeException('sin cuota'));

    $action = app(ResolveChatStartersAction::class);

    expect($action->execute(ClubName::CCM, RoleName::GOLF_MANAGER, library())[0]['area'])->toBe('Golf');

    fakeStarters(4);

    expect($action->execute(ClubName::CCM, RoleName::GOLF_MANAGER, library())[0]['question'])
        ->toBe('Pregunta 1');
});

it('falls back when the agent returns fewer than four starters', function (): void {
    fakeStarters(2);

    $starters = app(ResolveChatStartersAction::class)
        ->execute(ClubName::CCM, RoleName::PADDLE_MANAGER, library());

    expect($starters)->toHaveCount(4)
        ->and($starters[0]['area'])->toBe('Pádel')
        ->and(Cache::has('chat-starters:ccm:paddle_manager'))->toBeFalse();
});

it('uses the static starters without asking the agent when the library is empty', function (): void {
    ChatStarterSuggester::fake(fn () => throw new RuntimeException('no debería llamarse'));

    $starters = app(ResolveChatStartersAction::class)
        ->execute(ClubName::CCM, RoleName::ADMIN, new LibrarySnapshot);

    expect($starters)->toHaveCount(4)
        ->and($starters[0]['area'])->toBe('Dirección');
});

it('caches the starters per club and role for six hours', function (): void {
    fakeStarters(4);

    $action = app(ResolveChatStartersAction::class);
    $action->execute(ClubName::CCM, RoleName::GOLF_MANAGER, library());

    expect(Cache::has('chat-starters:ccm:golf_manager'))->toBeTrue()
        ->and(Cache::has('chat-starters:ccm:tennis_manager'))->toBeFalse();

    // Con el agente ya sin respuestas fingidas, la segunda llamada solo puede
    // devolver lo mismo si vino del caché.
    ChatStarterSuggester::fake(fn () => throw new RuntimeException('no debería llamarse'));

    expect($action->execute(ClubName::CCM, RoleName::GOLF_MANAGER, library())[0]['question'])
        ->toBe('Pregunta 1');
});

it('reads the indexed library from the database', function (): void {
    File::create(['name' => 'golf-a.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()->subDays(2)]);
    File::create(['name' => 'golf-b.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()->subDay()]);
    File::create(['name' => 'golf-old.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now(), 'expired_at' => now()]);
    File::create(['name' => 'golf-pending.md', 'group' => 'golf', 'status' => 'pending']);

    $totals = app(LibrarySnapshotQuery::class)->execute();

    expect($totals->documentCount)->toBe(2)
        ->and($totals->documents)->toBe([])
        ->and($totals->syncedAt->toDateString())->toBe(now()->subDay()->toDateString());

    $withDocuments = app(LibrarySnapshotQuery::class)->execute(withDocuments: true);

    expect($withDocuments->documents)->toHaveCount(2)
        ->and($withDocuments->documents[0]['name'])->toBe('golf-b.md');
});

it('reports an empty library when nothing is indexed', function (): void {
    expect(app(LibrarySnapshotQuery::class)->execute()->isEmpty())->toBeTrue()
        ->and(app(LibrarySnapshotQuery::class)->execute()->freshness())->toBeNull();
});
