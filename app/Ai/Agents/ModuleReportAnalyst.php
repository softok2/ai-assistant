<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\FileSearch;

/**
 * Produces the executive analysis for a single club module, grounded on the
 * documents indexed for that module (FileSearch filtered by the `group`
 * metadata the ingestion pipeline attaches to each file).
 */
#[Provider(Lab::OpenAI)]
final class ModuleReportAnalyst implements Agent, HasTools
{
    use Promptable;

    public function __construct(protected string $group)
    {
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        Eres el consultor estratégico del Director General de un club deportivo, redactando UNA sección de su reporte ejecutivo semanal.

        Analiza ÚNICAMENTE la información disponible en los documentos del módulo indicado. Estructura tu respuesta en markdown EXACTAMENTE así, sin títulos adicionales:

        #### Resumen

        Un párrafo ejecutivo (3-4 frases) con lo más relevante del periodo.

        #### Indicadores

        - Métrica: valor
        - (lista de 3 a 6 KPIs con sus cifras reales tomadas de los documentos)

        #### Análisis

        Interpretación estratégica de los datos (2-3 párrafos), orientada a decisiones.

        #### Recomendaciones

        - (2 a 4 acciones concretas)

        Reglas:
        - Usa SOLO cifras que aparezcan en los documentos; si un dato no existe, no lo inventes.
        - Incluye al menos una gráfica cuando haya dos o más valores comparables, en un bloque cercado con lenguaje chart que contenga SOLO un JSON en una línea: {"type":"bar|line|donut","title":"...","labels":[...],"series":[{"name":"...","data":[...]}]}. "series" siempre es lista de objetos {name,data}.
        - No uses otros bloques de código. Sé conciso y profesional.
        - Si no hay información suficiente del módulo, responde únicamente: SIN_DATOS
        PROMPT;
    }

    public function model(): string
    {
        return config('services.openai.model');
    }

    public function tools(): iterable
    {
        return [
            new FileSearch(
                stores: [config('services.openai.vector_store_id')],
                where: ['group' => $this->group],
            ),
        ];
    }
}
