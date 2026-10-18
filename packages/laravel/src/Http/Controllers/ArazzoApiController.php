<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Http\Controllers;

use Alama\Arazzo\Cli\Generator\ArazzoGenerator;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Laravel\Support\Payload;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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

        $data = Payload::record(json_decode((string) json_encode($resolved->content), true));
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

        $graph = Payload::record($request->input('graph'));

        // Convert graph to a natural language trace for the AI
        $trace = "Workflow Graph Intent:\n";
        foreach (Payload::records($graph, 'nodes') as $node) {
            $data = Payload::record($node['data'] ?? null);
            $id = Payload::text($node['id'] ?? null);
            $method = Payload::text($data['method'] ?? null);
            $path = Payload::text($data['path'] ?? null);
            $operationId = Payload::text($data['operationId'] ?? null);
            $trace .= "- Node {$id}: Execute {$method} {$path} (Operation: {$operationId}).\n";

            $parameters = $data['parameters'] ?? null;
            if (!empty($parameters)) {
                $trace .= '  - Parameters: '.str_replace("\n", ' ', Payload::text($parameters))."\n";
            }

            $requestBody = $data['requestBody'] ?? null;
            if (!empty($requestBody)) {
                $trace .= '  - Request Body: '.str_replace("\n", ' ', Payload::text($requestBody))."\n";
            }

            $criteria = $data['criteria'] ?? null;
            if (!empty($criteria)) {
                $trace .= '  - Success Criteria: '.str_replace("\n", ' ', Payload::text($criteria))."\n";
            }

            $outputs = $data['outputs'] ?? null;
            if (!empty($outputs)) {
                $trace .= '  - Outputs: '.str_replace("\n", ' ', Payload::text($outputs))."\n";
            }
        }
        foreach (Payload::records($graph, 'edges') as $edge) {
            $source = Payload::text($edge['source'] ?? null);
            $target = Payload::text($edge['target'] ?? null);
            $trace .= "- Edge: Output of Node {$source} flows to Node {$target}.\n";
        }

        $yaml = $generator->generate(Payload::text($request->input('openapi')), $trace);

        return response()->json(['yaml' => $yaml]);
    }
}
