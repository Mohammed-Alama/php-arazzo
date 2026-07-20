<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator;

use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;

class ArazzoGenerator
{
    public function __construct(private AiClientInterface $aiClient) {}

    public function generate(string $openapiSpec, string $workflowTrace): string
    {
        $systemPrompt = <<<'PROMPT'
You are an expert Arazzo specification generator. Arazzo is an OpenAPI-adjacent specification for describing workflows of sequential API calls.
Your goal is to output a strictly valid Arazzo (1.0.1) YAML document based on the user's provided OpenAPI specification and workflow trace.

Rules for Arazzo Generation:
1. Always start the document with `arazzo: 1.0.1` and an `info` block containing `title` and `version`.
2. Define `sourceDescriptions` linking to the OpenAPI (e.g., `name: api`, `type: openapi`).
3. Define `workflows` containing a `workflowId` and `steps`.
4. In each step, use `operationId` corresponding to the OpenAPI spec.
5. Extract values between steps using expressions (e.g., `{$steps.step1.response.body#/data/id}`).
6. Use parameters passing values (e.g., `in: path`, `name: userId`, `value: {$steps.step1.outputs.userId}`).
7. Output only the YAML block. Do not include markdown fences like ```yaml.
PROMPT;

        $userPrompt = <<<USER
Below is the OpenAPI Specification context:
{$openapiSpec}

Here is the intended Workflow Trace mapping out the sequence of steps:
{$workflowTrace}

Please generate the Arazzo YAML document:
USER;

        $yaml = $this->aiClient->generate($systemPrompt, $userPrompt);
        
        // Strip out markdown code blocks if the AI returned them despite the prompt
        $yaml = preg_replace('/^```yaml\s*|\s*```$/si', '', trim($yaml));
        
        return $yaml;
    }
}
