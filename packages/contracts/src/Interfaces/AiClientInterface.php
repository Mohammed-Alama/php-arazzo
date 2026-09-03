<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface AiClientInterface
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}
