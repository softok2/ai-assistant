<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[UseCheapestModel]
#[MaxTokens(120)]
final class ChatFollowUpSuggester implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Eres el asistente consultor de un club deportivo. Dado el último intercambio de la '
            .'conversación, propone exactamente 3 preguntas de seguimiento cortas (máximo 8 palabras cada una), '
            .'en español, que el director probablemente querría hacer a continuación.';
    }

    /**
     * @param  \Illuminate\JsonSchema\JsonSchemaTypeFactory  $schema
     */
    public function schema($schema): array
    {
        return [
            'suggestions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
