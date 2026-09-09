<?php

declare(strict_types=1);

use App\Models\File;
use Illuminate\Support\Collection;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolResult;
use Laravel\Ai\Streaming\Events\StreamStart;
use App\Actions\Chats\ResolveStreamInsightsAction;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;

function resolveInsights(array $events): array
{
    return app(ResolveStreamInsightsAction::class)->execute(new Collection($events))->toParts();
}

it('turns a finished file search into activity and a library source', function (): void {
    $parts = resolveInsights([
        new StreamStart('e0', 'openai', 'gpt-4o-mini', time()),
        new ProviderToolEvent('e1', 'fs_1', 'file_search_call', ['queries' => ['golf']], 'in_progress', time()),
        new ProviderToolEvent('e2', 'fs_1', 'file_search_call', ['queries' => ['golf']], 'completed', time()),
        new TextDelta('e3', 'm1', 'Hola', time()),
    ]);

    expect($parts['activity'])->toHaveCount(1)
        ->and($parts['activity'][0]['type'])->toBe('file_search')
        ->and($parts['activity'][0]['label'])->toBe('Consulté la biblioteca del club')
        ->and($parts['activity'][0]['status'])->toBe('completed')
        ->and($parts['sources'])->toHaveCount(1)
        ->and($parts['sources'][0]['kind'])->toBe('document')
        ->and($parts['sources'][0]['title'])->toBe('Biblioteca del club');
});

it('resolves file search results against the library by media id', function (): void {
    File::create([
        'name' => 'golf-semana.md',
        'group' => 'golf',
        'status' => 'completed',
        'assistant_media_id' => 'file-abc',
        'synced_at' => now()->subDay(),
    ]);

    $parts = resolveInsights([
        new ProviderToolEvent('e1', 'fs_1', 'file_search_call', [
            'results' => [
                ['file_id' => 'file-abc', 'filename' => 'golf-semana.md'],
            ],
        ], 'completed', time()),
    ]);

    expect($parts['sources'])->toHaveCount(1)
        ->and($parts['sources'][0]['kind'])->toBe('document')
        ->and($parts['sources'][0]['document_name'])->toBe('golf-semana.md')
        ->and($parts['sources'][0]['title'])->toBe('golf-semana')
        ->and($parts['sources'][0]['synced_at'])->not->toBeNull();
});

it('collects web search activity and url citations without duplicates', function (): void {
    $parts = resolveInsights([
        new ProviderToolEvent('e1', 'ws_1', 'web_search_call', [], 'completed', time()),
        new Citation('c1', 'm1', new UrlCitation('https://ejemplo.mx/nota', 'Nota de ejemplo'), time()),
        new Citation('c2', 'm1', new UrlCitation('https://ejemplo.mx/nota', 'Nota de ejemplo'), time()),
    ]);

    expect($parts['activity'])->toHaveCount(1)
        ->and($parts['activity'][0]['type'])->toBe('web_search')
        ->and($parts['activity'][0]['label'])->toBe('Busqué en la web')
        ->and($parts['sources'])->toHaveCount(1)
        ->and($parts['sources'][0]['kind'])->toBe('web')
        ->and($parts['sources'][0]['title'])->toBe('Nota de ejemplo')
        ->and($parts['sources'][0]['url'])->toBe('https://ejemplo.mx/nota');
});

it('reports regular tool calls with their own name and final status', function (): void {
    $call = new ToolCallData('t1', 'consultar_reservas', []);

    $parts = resolveInsights([
        new ToolCall('e1', $call, time()),
        new ToolResult('e2', new ToolResultData('t1', 'consultar_reservas', [], 'ok'), true, null, time()),
    ]);

    expect($parts['activity'])->toHaveCount(1)
        ->and($parts['activity'][0]['type'])->toBe('tool')
        ->and($parts['activity'][0]['label'])->toBe('consultar_reservas')
        ->and($parts['activity'][0]['status'])->toBe('completed')
        ->and($parts)->not->toHaveKey('sources');
});

it('marks a failed tool call and ignores unrelated events', function (): void {
    $parts = resolveInsights([
        new ToolCall('e1', new ToolCallData('t1', 'x', []), time()),
        new ToolResult('e2', new ToolResultData('t1', 'x', [], null), false, 'boom', time()),
        new TextDelta('e3', 'm1', 'texto', time()),
    ]);

    expect($parts['activity'][0]['status'])->toBe('failed');
});

it('returns nothing when the stream only carried text', function (): void {
    $parts = resolveInsights([
        new StreamStart('e0', 'openai', 'gpt-4o-mini', time()),
        new TextDelta('e1', 'm1', 'Hola', time()),
    ]);

    expect($parts)->toBe([]);
});
