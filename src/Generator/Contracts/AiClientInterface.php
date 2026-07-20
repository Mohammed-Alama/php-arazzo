<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator\Contracts;

interface AiClientInterface
{
    /**
     * Generate content based on system prompt and user prompt.
     */
    public function generate(string $systemPrompt, string $userPrompt): string;
}
