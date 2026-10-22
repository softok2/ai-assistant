<?php

declare(strict_types=1);

use Laravel\Ai\Attributes\MaxTokens;
use App\Ai\Agents\ChatTitleGenerator;
use App\Ai\Agents\ChatStarterSuggester;
use App\Ai\Agents\ChatFollowUpSuggester;
use App\Ai\Agents\ExecutiveSummaryWriter;

function maxTokensOf(string $agent): int
{
    $attributes = new ReflectionClass($agent)->getAttributes(MaxTokens::class);

    expect($attributes)->toHaveCount(1);

    return $attributes[0]->getArguments()[0];
}

// Con un modelo de razonamiento los tokens de razonamiento cuentan dentro de
// max_output_tokens: un tope pensado solo para el texto deja la respuesta vacía.
it('leaves room for reasoning tokens in the agents with an output cap', function (string $agent, int $minimum): void {
    expect(maxTokensOf($agent))->toBeGreaterThanOrEqual($minimum);
})->with([
    'executive summary' => [ExecutiveSummaryWriter::class, 2000],
    'chat title' => [ChatTitleGenerator::class, 400],
    'follow-up suggestions' => [ChatFollowUpSuggester::class, 800],
    'chat starters' => [ChatStarterSuggester::class, 1200],
]);
