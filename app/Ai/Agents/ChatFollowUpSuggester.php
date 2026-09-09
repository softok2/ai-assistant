<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\User;
use App\Enums\ClubName;
use App\Enums\RoleName;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

#[Provider(Lab::OpenAI)]
#[UseCheapestModel]
#[MaxTokens(120)]
final class ChatFollowUpSuggester implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected ?ClubName $club = null,
        protected ?RoleName $role = null,
        protected ?string $userName = null,
    ) {}

    public static function forUser(?User $user): self
    {
        return new self(
            club: $user?->clubName(),
            role: $user?->primaryRole(),
            userName: $user?->name,
        );
    }

    public function instructions(): string
    {
        $club = $this->club?->label() ?? 'un club deportivo';
        $role = $this->role?->label() ?? 'la dirección del club';
        $areas = $this->role?->areas() ?? [];

        return 'Eres el asistente consultor de '.$club.'. Dado el último intercambio de la '
            .'conversación, propone exactamente 3 preguntas de seguimiento cortas (máximo 8 palabras cada una), '
            .'en español, que '.$role.' probablemente querría hacer a continuación.'
            .($areas === [] ? '' : ' Prioriza estas áreas: '.implode(', ', $areas).'.');
    }

    /**
     * @param  JsonSchemaTypeFactory  $schema
     */
    public function schema($schema): array
    {
        return [
            'suggestions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
