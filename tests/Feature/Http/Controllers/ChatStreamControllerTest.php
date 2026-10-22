<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use App\Models\Chat;
use App\Models\Role;
use App\Models\User;
use App\Ai\Agents\ClubAssistant;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Gateway\FakeTextGateway;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ChatStreamController', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->chat = Chat::factory()->for($this->user)->create();
    });

    it('streams the agent response and persists both messages', function (): void {
        ClubAssistant::fake(['Hola, ¿en qué puedo ayudarte hoy?']);

        $response = $this->post(route('chat.stream', $this->chat), [
            'message' => 'Hola',
        ]);

        $response->assertOk();
        $response->assertStreamed();

        $content = $response->streamedContent();
        expect($content)->toContain('text_delta');
        expect($content)->toContain('[DONE]');

        expect($this->chat->messages()->where('role', 'user')->count())->toBe(1);

        $assistantMessage = $this->chat->messages()->where('role', 'assistant')->first();
        expect($assistantMessage)->not->toBeNull();
        expect($assistantMessage->parts['text'])->toBe('Hola, ¿en qué puedo ayudarte hoy?');
    });

    it('sends prior conversation history to the agent', function (): void {
        $fake = ClubAssistant::fake(['Segunda respuesta']);

        $this->chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => 'Primer mensaje'],
            'attachments' => '[]',
        ]);
        $this->chat->messages()->create([
            'role' => 'assistant',
            'parts' => ['text' => 'Primera respuesta'],
            'attachments' => '[]',
        ]);

        $this->post(route('chat.stream', $this->chat), [
            'message' => 'Segundo mensaje',
        ])->assertOk()->streamedContent();

        expect($this->chat->messages()->count())->toBe(4);
    });

    it('rejects streaming into a chat owned by another user', function (): void {
        ClubAssistant::fake(['no debería enviarse']);

        $otherChat = Chat::factory()->for(User::factory()->create())->create();

        $this->post(route('chat.stream', $otherChat), [
            'message' => 'Hola',
        ])->assertForbidden();

        expect($otherChat->messages()->count())->toBe(0);
    });

    it('ends the stream with a terminal error and [DONE] when the provider fails mid-stream', function (string $failure, bool $reported): void {
        Exceptions::fake();
        ClubAssistant::fake();

        // Streams the first text delta, then fails the way the given scenario describes.
        $gateway = new class(['Primera parte de la respuesta'], $failure) extends FakeTextGateway
        {
            public function __construct(array $responses, private readonly string $failure)
            {
                parent::__construct($responses);
            }

            public function generateStreamStep(
                string $invocationId,
                TextProvider $provider,
                string $model,
                ?string $instructions,
                array $messages,
                array $tools,
                ?array $schema,
                ?TextGenerationOptions $options,
                ?int $timeout,
                StepContext $stepContext,
            ): Generator {
                $events = parent::generateStreamStep(
                    $invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext,
                );

                foreach ($events as $event) {
                    yield $event;

                    if (! $event instanceof TextDelta) {
                        continue;
                    }

                    if ($this->failure === 'exception') {
                        throw new RuntimeException('gateway exploded');
                    }

                    // Like a real provider: emit the error event and end the step without a response.
                    yield new Error('evt', 'insufficient_quota', 'Sin créditos', false, time())->withInvocationId($invocationId);

                    return null;
                }
            }
        };

        (fn (): object => $this->fakeAgentGateways[ClubAssistant::class] = $gateway)->call(Ai::getFacadeRoot());

        $response = $this->post(route('chat.stream', $this->chat), ['message' => 'Hola']);

        $response->assertOk();
        $response->assertStreamed();

        $content = $response->streamedContent();

        expect($content)->toContain('"type":"text_delta"')
            ->and($content)->toContain('Primera')
            ->and(mb_substr_count($content, '"recoverable":false'))->toBe(1)
            ->and($content)->toEndWith("data: [DONE]\n\n");

        expect($this->chat->messages()->where('role', 'assistant')->count())->toBe(0);

        if ($reported) {
            expect($content)->toContain('data: {"type":"error","message":"stream_failed","recoverable":false}');
            Exceptions::assertReported(RuntimeException::class);
        } else {
            Exceptions::assertNothingReported();
        }
    })->with([
        // laravel/ai forwards the provider error event, then throws StreamErrorException.
        'provider error event' => ['provider_error', false],
        // Any other failure (e.g. a gateway HTTP error) becomes a masked "type":"error" event.
        'unexpected exception' => ['exception', true],
    ]);

    it('validates the message payload', function (): void {
        $this->post(route('chat.stream', $this->chat), [
            'message' => str_repeat('a', 4001),
        ])->assertSessionHasErrors('message');

        $this->post(route('chat.stream', $this->chat), [
            'message' => 'Hola',
            'model' => 'modelo-inexistente',
        ])->assertSessionHasErrors('model');
    });
});

describe('regenerate', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->chat = Chat::factory()->for($this->user)->create();
    });

    it('replaces the last assistant response without duplicating the user message', function (): void {
        ClubAssistant::fake(['Respuesta regenerada']);

        $this->chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'Pregunta'], 'attachments' => []]);
        $this->chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'Respuesta original'], 'attachments' => []]);

        $this->post(route('chat.stream', $this->chat), ['regenerate' => true])
            ->assertOk()
            ->streamedContent();

        expect($this->chat->messages()->where('role', 'user')->count())->toBe(1);
        expect($this->chat->messages()->where('role', 'assistant')->count())->toBe(1);
        expect($this->chat->messages()->where('role', 'assistant')->first()->parts['text'])->toBe('Respuesta regenerada');
    });

    it('rejects regeneration on an empty chat', function (): void {
        ClubAssistant::fake(['nada']);

        $this->post(route('chat.stream', $this->chat), ['regenerate' => true])
            ->assertStatus(422);
    });
});

describe('proveedor de IA por club', function (): void {
    it('falls back to the default provider for a user without a club', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);
        $chat = Chat::factory()->for($user)->create();

        ClubAssistant::fake(['Respuesta']);

        $this->post(route('chat.stream', $chat), ['message' => 'Hola'])
            ->assertOk()
            ->streamedContent();

        ClubAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai');
    });
});

describe('contexto y partes del mensaje', function (): void {
    beforeEach(function (): void {
        config(['services.openai.vector_stores' => ['vallealto' => 'vs_test']]);
        $this->user = User::factory()->create(['name' => 'Ana Gómez', 'club_name' => 'vallealto']);
        $this->user->roles()->attach(Role::query()->firstOrCreate(['name' => 'golf_manager']));
        $this->actingAs($this->user);
        $this->chat = Chat::factory()->for($this->user)->create();
    });

    it('builds the assistant with the club, role and name of the signed-in user', function (): void {
        $instructions = ClubAssistant::forUser($this->user)->instructions();

        expect($instructions)->toContain('Club Valle Alto')
            ->and($instructions)->toContain('Gerencia de golf')
            ->and($instructions)->toContain('Ana Gómez');
    });

    it('persists a kpi block exactly as the model wrote it', function (): void {
        $answer = "Buena semana en golf.\n\n```kpi\n{\"items\":[{\"label\":\"Reservas\",\"value\":\"842\","
            ."\"delta\":\"+6% vs. semana anterior\",\"tone\":\"good\"}]}\n```\n\n#### Qué haría\n\n1. Nada.";

        ClubAssistant::fake([$answer]);

        $this->post(route('chat.stream', $this->chat), ['message' => 'KPIs de golf'])
            ->assertOk()
            ->streamedContent();

        expect($this->chat->messages()->where('role', 'assistant')->first()->parts['text'])->toBe($answer);
    });

    it('leaves activity and sources out when the stream carried no tool events', function (): void {
        ClubAssistant::fake(['Respuesta sin herramientas']);

        $this->post(route('chat.stream', $this->chat), ['message' => 'Hola'])
            ->assertOk()
            ->streamedContent();

        $parts = $this->chat->messages()->where('role', 'assistant')->first()->parts;

        expect($parts)->toHaveKey('text')
            ->and($parts)->not->toHaveKey('activity')
            ->and($parts)->not->toHaveKey('sources');
    });

    it('prompts the club provider for a user whose club has its own key', function (): void {
        config(['ai.providers.openai_vallealto.key' => 'sk-va']);

        ClubAssistant::fake(['Respuesta']);

        $this->post(route('chat.stream', $this->chat), ['message' => 'Hola'])
            ->assertOk()
            ->streamedContent();

        ClubAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai_vallealto');
    });
});
