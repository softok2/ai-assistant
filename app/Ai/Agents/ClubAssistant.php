<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\User;
use App\Enums\ClubName;
use App\Enums\RoleName;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\Message;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Attributes\Provider;
use App\Models\Message as ChatMessage;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Providers\Tools\FileSearch;

#[Provider(Lab::OpenAI)]
final class ClubAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  Collection<int, ChatMessage>|null  $history  prior chat messages, oldest first
     */
    public function __construct(
        protected ?Collection $history = null,
        protected ?ClubName $club = null,
        protected ?RoleName $role = null,
        protected ?string $userName = null,
    ) {}

    /**
     * Arma el asistente con el club, el rol y el nombre de quien pregunta.
     *
     * @param  Collection<int, ChatMessage>|null  $history
     */
    public static function forUser(?User $user, ?Collection $history = null): self
    {
        return new self(
            history: $history,
            club: $user?->clubName(),
            role: $user?->primaryRole(),
            userName: $user?->name,
        );
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        $prompt = file_get_contents(resource_path('prompts/club_assistant.md'));

        $prompt = str_replace(
            ['{{club}}', '{{role}}'],
            [$this->clubLabel(), $this->roleLabel()],
            $prompt
        );

        return $prompt."\n\n".$this->contextBlock();
    }

    /**
     * Get the model the agent should use.
     */
    public function model(): string
    {
        return config('services.openai.model');
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return ($this->history ?? new Collection)
            ->map(fn (ChatMessage $message): Message => new Message(
                $message->role,
                $message->parts['text'] ?? ''
            ))
            ->all();
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new FileSearch(stores: [config('services.openai.vector_store_id')]),
            new WebSearch,
        ];
    }

    /**
     * Quién pregunta, desde qué club y qué áreas le tocan. Va al final del
     * prompt para que pese más que el formato.
     */
    private function contextBlock(): string
    {
        $lines = [
            'Contexto de esta conversación:',
            '',
            'Club: '.$this->clubLabel().'.',
            'Rol de quien pregunta: '.$this->roleLabel().'.',
            'Áreas que atiende ese rol: '.$this->areasLabel().'.',
        ];

        if ($this->userName !== null && trim($this->userName) !== '') {
            $lines[] = 'Nombre de quien pregunta: '.trim($this->userName).'.';
        }

        $lines[] = '';
        $lines[] = 'Habla en segunda persona a '.$this->addressee()
            .'; prioriza las áreas de su rol; si la pregunta sale de ellas responde igual pero dilo.';

        return implode("\n", $lines);
    }

    private function clubLabel(): string
    {
        return $this->club?->label() ?? 'el club';
    }

    private function roleLabel(): string
    {
        return $this->role?->label() ?? 'la dirección del club';
    }

    private function areasLabel(): string
    {
        $areas = $this->role?->areas() ?? [];

        return $areas === [] ? 'todas las áreas del club' : implode(', ', $areas);
    }

    private function addressee(): string
    {
        return $this->userName !== null && trim($this->userName) !== ''
            ? trim($this->userName)
            : 'quien pregunta';
    }
}
