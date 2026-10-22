<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use Laravel\Ai\Enums\Lab;

/**
 * Pide razonamiento a OpenAI con el esfuerzo configurado en
 * `services.openai.reasoning_effort`. Solo para modelos que razonan: OpenAI
 * rechaza el parámetro `reasoning` en los demás.
 */
trait ConfiguresOpenAiReasoning
{
    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;
        $effort = config('services.openai.reasoning_effort');

        if ($driver !== Lab::OpenAI->value || blank($effort) || ! $this->supportsReasoning($this->model())) {
            return [];
        }

        return ['reasoning' => array_filter([
            'effort' => $effort,
            'summary' => $this->reasoningSummary(),
        ])];
    }

    /**
     * Resumen legible del razonamiento (`auto`), o null para no pedirlo.
     */
    protected function reasoningSummary(): ?string
    {
        return null;
    }

    private function supportsReasoning(string $model): bool
    {
        return preg_match('/^(gpt-5(?!-chat)|gpt-6|o\d)/', $model) === 1;
    }
}
