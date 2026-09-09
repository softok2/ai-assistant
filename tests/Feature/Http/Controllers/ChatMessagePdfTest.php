<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;
use App\Models\Message;
use App\Reports\ChartSvgRenderer;
use App\Reports\MessagePdfRenderer;
use App\Reports\ReportContentParser;
use League\CommonMark\CommonMarkConverter;
use App\Reports\Contracts\RendersMessageAsPdf;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->chat = Chat::factory()->for($this->user)->create(['title' => 'Ocupación de Restaurantes']);
});

function assistantMessage(Chat $chat, string $text): Message
{
    return Message::factory()->for($chat)->create([
        'role' => 'assistant',
        'parts' => ['text' => $text],
        'attachments' => [],
    ]);
}

it('exports an assistant message as a pdf named after the chat', function (): void {
    $this->mock(RendersMessageAsPdf::class, function ($mock): void {
        $mock->shouldReceive('render')->once()->andReturn('%PDF-fake');
        $mock->shouldReceive('filename')->once()->andReturn('ocupacion-de-restaurantes.pdf');
    });

    $response = $this->post(route('chat.messages.pdf', assistantMessage($this->chat, 'Hola')));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('ocupacion-de-restaurantes.pdf');
});

it('throttles the export after ten requests in a minute', function (): void {
    $this->mock(RendersMessageAsPdf::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn('%PDF-fake');
        $mock->shouldReceive('filename')->andReturn('respuesta.pdf');
    });

    $message = assistantMessage($this->chat, 'Hola');

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->post(route('chat.messages.pdf', $message))->assertOk();
    }

    $this->post(route('chat.messages.pdf', $message))->assertStatus(429);
});

it('refuses to export a message from a private chat of someone else', function (): void {
    $other = Chat::factory()->for(User::factory()->create())->create(['visibility' => 'private']);

    $this->post(route('chat.messages.pdf', assistantMessage($other, 'Hola')))->assertForbidden();
});

it('does not export user messages', function (): void {
    $message = Message::factory()->for($this->chat)->create([
        'role' => 'user',
        'parts' => ['text' => 'Hola'],
        'attachments' => [],
    ]);

    $this->post(route('chat.messages.pdf', $message))->assertNotFound();
});

it('slugs the chat title for the file name', function (): void {
    $renderer = new MessagePdfRenderer(new ReportContentParser(new ChartSvgRenderer, new CommonMarkConverter));

    expect($renderer->filename(assistantMessage($this->chat, 'Hola')))
        ->toBe('ocupacion-de-restaurantes.pdf');
});

it('pulls kpi tiles and charts out of the markdown before rendering', function (): void {
    $parser = new ReportContentParser(new ChartSvgRenderer, new CommonMarkConverter);

    $parsed = $parser->parse(
        "Buena semana.\n\n```kpi\n{\"items\":[{\"label\":\"Reservas\",\"value\":\"842\",\"delta\":\"+6%\",\"tone\":\"good\"}]}\n```\n\n"
        ."```chart\n{\"type\":\"bar\",\"labels\":[\"A\"],\"series\":[{\"data\":[1]}]}\n```"
    );

    expect($parsed['kpis'])->toHaveCount(1)
        ->and($parsed['kpis'][0])->toBe(['label' => 'Reservas', 'value' => '842', 'delta' => '+6%', 'tone' => 'good'])
        ->and($parsed['charts'])->toHaveCount(1)
        ->and($parsed['html'])->toContain('Buena semana')
        ->and($parsed['html'])->not->toContain('842');
});

it('ignores a malformed kpi block instead of breaking the export', function (): void {
    $parser = new ReportContentParser(new ChartSvgRenderer, new CommonMarkConverter);

    expect($parser->parse("Hola\n\n```kpi\nno es json\n```")['kpis'])->toBe([]);
});
