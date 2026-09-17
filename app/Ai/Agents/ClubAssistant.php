<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\User;
use App\Enums\ClubName;
use App\Enums\RoleName;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\Message;
use App\Ai\Files\ClubVectorStore;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Attributes\Provider;
use App\Queries\LibrarySnapshotQuery;
use App\Models\Message as ChatMessage;
use App\Ai\Files\MissingClubVectorStore;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\FileSearchQuery;

#[Provider(Lab::OpenAI)]
final class ClubAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  Collection<int, ChatMessage>|null  $history  prior chat messages, oldest first
     * @param  Carbon|null  $dataCutoff  última actualización de la biblioteca del club
     */
    public function __construct(
        protected ?Collection $history = null,
        protected ?ClubName $club = null,
        protected ?RoleName $role = null,
        protected ?string $userName = null,
        protected ?Carbon $dataCutoff = null,
    ) {}

    /**
     * Arma el asistente con el club, el rol y el nombre de quien pregunta.
     *
     * @param  Collection<int, ChatMessage>|null  $history
     */
    public static function forUser(?User $user, ?Collection $history = null): self
    {
        $club = $user?->clubName();
        $role = $user?->primaryRole();

        return new self(
            history: $history,
            club: $club,
            role: $role,
            userName: $user?->name,
            dataCutoff: app(LibrarySnapshotQuery::class)->execute($club, $role)->syncedAt,
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
     * Herramientas del agente. La búsqueda de documentos va SIEMPRE al store
     * del club del usuario (frontera dura entre clubes) y, si el rol es de una
     * sola área, se recorta a sus grupos. Sin club no hay documentos que
     * buscar: solo queda la web.
     */
    public function tools(): iterable
    {
        if ($this->club === null) {
            return [new WebSearch];
        }

        try {
            $store = app(ClubVectorStore::class)->idFor($this->club);
        } catch (MissingClubVectorStore) {
            return [new WebSearch];
        }

        $groups = $this->role?->sourceGroups() ?? [];

        return [
            $groups === []
                ? new FileSearch(stores: [$store])
                : new FileSearch(stores: [$store], where: fn (FileSearchQuery $query) => $query->whereIn('group', $groups)),
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

        if ($this->dataCutoff !== null) {
            $lines[] = 'Última actualización de los reportes: '.$this->dataCutoff->format('Y-m-d H:i').'.';
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
