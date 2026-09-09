<?php

declare(strict_types=1);

namespace App\Ai\Agents;

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

/**
 * Propone las cuatro preguntas de arranque de la pantalla de inicio a partir
 * del rol de quien entra y de los documentos que la biblioteca tiene
 * indexados, para no sugerir cosas que el asistente no puede responder.
 */
#[Provider(Lab::OpenAI)]
#[UseCheapestModel]
#[MaxTokens(300)]
final class ChatStarterSuggester implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected ?ClubName $club = null,
        protected ?RoleName $role = null,
    ) {}

    public function instructions(): string
    {
        $club = $this->club?->label() ?? 'un club deportivo';
        $role = $this->role?->label() ?? 'la dirección del club';
        $areas = $this->role?->areas() ?? [];

        return 'Eres el asistente consultor de '.$club.'. Quien entra es '.$role.'.'
            .($areas === [] ? '' : ' Sus áreas son: '.implode(', ', $areas).'.')
            .' A partir de los documentos indexados que te paso, propone exactamente 4 preguntas '
            .'de arranque en español, cortas (máximo 10 palabras), que se puedan responder con esos '
            .'documentos. Cada una con el área a la que pertenece. No repitas áreas si puedes evitarlo '
            .'y no inventes datos que los documentos no tengan.';
    }

    /**
     * @param  JsonSchemaTypeFactory  $schema
     */
    public function schema($schema): array
    {
        return [
            'starters' => $schema->array()->items($schema->object([
                'area' => $schema->string()->required(),
                'question' => $schema->string()->required(),
            ]))->required(),
        ];
    }
}
