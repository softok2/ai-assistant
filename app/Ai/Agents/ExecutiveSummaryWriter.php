<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Writes the cover executive summary from the already-produced module sections.
 */
#[Provider(Lab::OpenAI)]
#[MaxTokens(400)]
final class ExecutiveSummaryWriter implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Eres el consultor del Director General de un club deportivo. A partir de los análisis por '
            .'módulo que recibes, redacta un RESUMEN EJECUTIVO GLOBAL en español (máximo 2 párrafos, sin títulos '
            .'ni listas): destaca lo más relevante de la semana, riesgos y oportunidades transversales. '
            .'Tono estratégico y directo. No inventes cifras que no aparezcan en los análisis.';
    }

    public function model(): string
    {
        return config('services.openai.model');
    }
}
