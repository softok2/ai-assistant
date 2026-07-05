<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[UseCheapestModel]
#[MaxTokens(30)]
final class ChatTitleGenerator implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Genera un título breve en español (máximo 6 palabras, sin comillas ni punto final) '
            .'que resuma el tema de la conversación. Responde SOLO con el título.';
    }
}
