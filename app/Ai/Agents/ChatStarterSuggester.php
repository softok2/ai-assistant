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
 *
 * La biblioteca son cortes del almacén BI, no la operación en vivo: sin
 * decírselo, el modelo proponía "¿cómo está el campo hoy?" o "el menú de hoy",
 * preguntas que ningún documento puede contestar.
 */
#[Provider(Lab::OpenAI)]
#[UseCheapestModel]
#[MaxTokens(300)]
final class ChatStarterSuggester implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string|null  $cutoff  fecha del corte más reciente de la biblioteca (Y-m-d)
     */
    public function __construct(
        protected ?ClubName $club = null,
        protected ?RoleName $role = null,
        protected ?string $cutoff = null,
    ) {}

    public function instructions(): string
    {
        $club = $this->club?->label() ?? 'un club deportivo';
        $role = $this->role?->label() ?? 'la dirección del club';
        $areas = $this->role?->areas() ?? [];

        return 'Eres el asistente consultor de '.$club.'. Quien entra es '.$role.'.'
            .($areas === [] ? '' : ' Sus áreas son: '.implode(', ', $areas).'.')
            .' Los documentos indexados son cortes del almacén BI'
            .($this->cutoff === null ? '' : ', el más reciente del '.$this->cutoff)
            .': cifras agregadas de periodos ya cerrados. El día en curso nunca está, así que '
            .'no propongas preguntas de tiempo real: nada de hoy, ahora, en este momento, '
            .'disponibilidad, lugares libres, menús ni estado de las instalaciones.'
            .' Prioriza las áreas de reportes: golf, incidencias y deportes (tenis, pádel, fútbol); '
            .'usa otra área solo si ahí hay algo mejor que preguntar. Si el rol de quien entra cubre '
            .'unas pocas áreas, esas mandan sobre esa prioridad.'
            .' A partir de los documentos indexados que te paso, propone exactamente 4 preguntas '
            .'de arranque en español, cortas (máximo 10 palabras), que se puedan responder con esos '
            .'documentos sobre periodos cerrados: la semana pasada, el mes, el trimestre, o contra '
            .'el año pasado. Cada una con el área a la que pertenece. No repitas áreas si puedes '
            .'evitarlo y no inventes datos que los documentos no tengan.';
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
