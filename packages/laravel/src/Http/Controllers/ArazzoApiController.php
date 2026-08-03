<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Http\Controllers;

use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Resolution\SourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ArazzoApiController extends Controller
{
    public function endpoints(Request $request, SourceResolver $resolver): JsonResponse
    {
        $specPath = $request->query('spec');
        if (!$specPath) {
            return response()->json(['error' => 'spec parameter is required'], 400);
        }

        $source = new SourceDescription('api', is_string($specPath) ? $specPath : '', SourceType::Openapi);
        $resolved = $resolver->resolve($source, getcwd() ?: '');

        $data = json_decode((string) json_encode($resolved->extract('/')), true);
        $endpoints = [];

        if (isset($data['paths']) && is_array($data['paths'])) {
            foreach ($data['paths'] as $path => $methods) {
                foreach ((array) $methods as $method => $op) {
                    if (is_array($op) && isset($op['operationId'])) {
                        $endpoints[] = [
                            'method' => strtoupper($method),
                            'path' => $path,
                            'operationId' => $op['operationId'],
                            'summary' => $op['summary'] ?? '',
                            'description' => $op['description'] ?? '',
                            'tags' => $op['tags'] ?? ['Default'],
                        ];
                    }
                }
            }
        }

        return response()->json($endpoints);
    }

    public function generate(Request $request, ArazzoGenerator $generator): JsonResponse
    {
        $request->validate([
            'openapi' => 'required|string',
            'graph' => 'required|array',
        ]);

        $graph = $request->input('graph');

        // Convert graph to a natural language trace for the AI
        $trace = "Workflow Graph Intent:\n";
        foreach ($graph['nodes'] ?? [] as $node) {
            $trace .= "- Node {$node['id']}: Execute {$node['data']['method']} {$node['data']['path']} (Operation: {$node['data']['operationId']}).\n";
            if (!empty($node['data']['parameters'])) {
                $trace .= '  - Parameters: ' . str_replace("\n", ' ', $node['data']['parameters']) . "\n";
            }
            if (!empty($node['data']['requestBody'])) {
                $trace .= '  - Request Body: ' . str_replace("\n", ' ', $node['data']['requestBody']) . "\n";
            }
            if (!empty($node['data']['criteria'])) {
                $trace .= '  - Success Criteria: ' . str_replace("\n", ' ', $node['data']['criteria']) . "\n";
            }
            if (!empty($node['data']['outputs'])) {
                $trace .= '  - Outputs: ' . str_replace("\n", ' ', $node['data']['outputs']) . "\n";
            }
        }
        foreach ($graph['edges'] ?? [] as $edge) {
            $trace .= "- Edge: Output of Node {$edge['source']} flows to Node {$edge['target']}.\n";
        }

        $yaml = $generator->generate($request->input('openapi'), $trace);

        return response()->json(['yaml' => $yaml]);
    }
}
