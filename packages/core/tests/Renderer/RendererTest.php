<?php

declare(strict_types=1);

use Alama\Arazzo\Console\DocumentLoader;
use Alama\Arazzo\Renderer\Renderer;

const RENDER_ACTIONS = __DIR__ . '/../fixtures/renderer/actions.yaml';

it('renders a mermaid flowchart with retry and goto edges', function (): void {
    $document = DocumentLoader::load(RENDER_ACTIONS);
    $mermaid = (new Renderer())->toMermaid($document);

    expect($mermaid)->toStartWith('flowchart TD')
        ->and($mermaid)->toContain('subgraph id_flow["workflow: flow"]')
        ->and($mermaid)->toContain('n1["fetch')
        ->and($mermaid)->toContain('-.->|✘ retry| n1')
        ->and($mermaid)->toContain('-.->|✘ goto| n2')
        // explicit end action terminates the chart instead of chaining
        ->and($mermaid)->toContain('n2 --> done')
        ->and($mermaid)->not->toContain('n2 --> n3');
});

it('can scope the mermaid chart to one workflow', function (): void {
    $document = DocumentLoader::load(RENDER_ACTIONS);
    $mermaid = (new Renderer())->toMermaid($document, 'flow');

    expect($mermaid)->toContain('id_flow')
        ->and($mermaid)->not->toContain('id_other');
});

it('renders markdown docs with sources, steps, criteria, and outputs', function (): void {
    $document = DocumentLoader::load(RENDER_ACTIONS);
    $markdown = (new Renderer())->toMarkdown($document);

    expect($markdown)->toStartWith('# Render demo')
        ->and($markdown)->toContain('| api | openapi | https://api.test/openapi.json |')
        ->and($markdown)->toContain('## Workflow `flow`')
        ->and($markdown)->toContain('`${response.statusCode} == 200`') // condition text
        ->and($markdown)->toContain('`item` = `$response.body#/item`')
        ->and($markdown)->toContain('**Workflow outputs:**');
});
