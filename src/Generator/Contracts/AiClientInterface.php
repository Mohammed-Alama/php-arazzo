<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator\Contracts;

interface AiClientInterface
{
    /**
     * Generate content based on system instructions and user trace.
     *
     * @param string $systemInstructions
     * @param string $userTrace
     * @return string
     */
    public function generate(string $systemInstructions, string $userTrace): string;
}
