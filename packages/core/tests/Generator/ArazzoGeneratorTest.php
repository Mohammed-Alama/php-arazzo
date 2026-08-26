<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Contracts\AiClientInterface;
use Alama\Arazzo\Generator\ArazzoGenerator;

it('generates arazzo yaml from openapi and trace', function () {
    $aiClient = new class() implements AiClientInterface
    {
        public string $lastSystemPrompt = '';

        public string $lastUserPrompt = '';

        public function generate(string $systemPrompt, string $userPrompt): string
        {
            $this->lastSystemPrompt = $systemPrompt;
            $this->lastUserPrompt = $userPrompt;

            return "arazzo: 1.0.1\ninfo:\n  title: Test\n  version: 1.0.0";
        }
    };

    $generator = new ArazzoGenerator($aiClient);

    $openapi = "openapi: 3.0.0\ninfo:\n  title: API\n  version: 1.0";
    $trace = "Workflow Graph Intent:\n- Node 1: Execute GET /users";

    $yaml = $generator->generate($openapi, $trace);

    expect($yaml)->toContain('arazzo: 1.0.1');
    expect($aiClient->lastSystemPrompt)->toContain('You are an expert Arazzo specification generator');
    expect($aiClient->lastUserPrompt)->toContain($trace);
    expect($aiClient->lastUserPrompt)->toContain($openapi);
});

it('strips markdown code blocks from the generated yaml', function () {
    $aiClient = new class() implements AiClientInterface
    {
        public function generate(string $systemPrompt, string $userPrompt): string
        {
            return "```yaml\narazzo: 1.0.1\ninfo:\n  title: Test\n  version: 1.0.0\n```";
        }
    };

    $generator = new ArazzoGenerator($aiClient);

    $openapi = 'openapi: 3.0.0';
    $trace = "Workflow Graph Intent:\n- Node 1: Execute GET /users";

    $yaml = $generator->generate($openapi, $trace);

    expect($yaml)->not->toContain('```yaml');
    expect($yaml)->not->toContain('```');
    expect($yaml)->toContain('arazzo: 1.0.1');
});
